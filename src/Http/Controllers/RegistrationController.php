<?php

/**
 * This file is part of SeAT Discord Connector.
 *
 * Copyright (C) 2019, 2020  Warlof Tutsimo <loic.leuilliot@gmail.com>
 *
 * SeAT Discord Connector  is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * SeAT Discord Connector is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Warlof\Seat\Connector\Drivers\Discord\Http\Controllers;

use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Seat\Web\Http\Controllers\Controller;
use SocialiteProviders\Manager\Config;
use Warlof\Seat\Connector\Drivers\Discord\Driver\DiscordClient;
use Warlof\Seat\Connector\Drivers\Discord\Helpers\Helper;
use Warlof\Seat\Connector\Drivers\IClient;
use Warlof\Seat\Connector\Events\EventLogger;
use Warlof\Seat\Connector\Exceptions\DriverSettingsException;
use Warlof\Seat\Connector\Models\User;

/**
 * Class RegistrationController.
 *
 * @package Warlof\Seat\Connector\Discord\Http\Controllers
 */
class RegistrationController extends Controller
{
    const SCOPES = [
        'identify', 'guilds.join',
    ];

    const SCOPES_WITH_EMAIL = [
        'identify', 'email', 'guilds.join',
    ];

    /**
     * @return mixed
     * @throws \Seat\Services\Exceptions\SettingException
     * @throws \Warlof\Seat\Connector\Exceptions\DriverSettingsException
     */
    public function redirectToProvider()
    {
        $settings = setting('seat-connector.drivers.discord', true);

        if (is_null($settings) || ! is_object($settings))
            throw new DriverSettingsException('The Driver has not been configured yet.');

        if (! property_exists($settings, 'client_id') || is_null($settings->client_id) || $settings->client_id == '')
            throw new DriverSettingsException('Parameter client_id is missing.');

        if (! property_exists($settings, 'client_secret') || is_null($settings->client_secret) || $settings->client_secret == '')
            throw new DriverSettingsException('Parameter client_secret is missing.');

        if (! property_exists($settings, 'use_email_scope') || is_null($settings->use_email_scope))
            throw new DriverSettingsException('Parameter use_email_scope is missing.');

        $redirect_uri = route('seat-connector.drivers.discord.registration.callback');

        $config = new Config($settings->client_id, $settings->client_secret, $redirect_uri);

        return Socialite::driver('discord')->setConfig($config)->setScopes(($settings->use_email_scope == 1) ? self::SCOPES_WITH_EMAIL : self::SCOPES)->redirect();
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Seat\Services\Exceptions\SettingException
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function handleProviderCallback()
    {
        // retrieve driver instance
        $client = DiscordClient::getInstance();

        $settings = setting('seat-connector.drivers.discord', true);

        $redirect_uri = route('seat-connector.drivers.discord.registration.callback');

        $config = new Config($settings->client_id, $settings->client_secret, $redirect_uri);

        // retrieve authenticated user
        $socialite_user = Socialite::driver('discord')->setConfig($config)->user();

        // update or create the connector user
        $original_user = User::where('connector_type', 'discord')->where('user_id', auth()->user()->id)->first();

        // if connector ID is a new one - revoke existing access on the old ID
        if (! is_null($original_user) && $original_user->connector_id != $socialite_user->id)
            $this->revokeOldIdentity($client, $original_user);

        // spawn or update existing identity using returned information
        $driver_user = User::updateOrCreate([
            'connector_type' => 'discord',
            'user_id'        => auth()->user()->id,
        ], [
            'connector_id'   => $socialite_user->id,
            'unique_id'      => ($settings->use_email_scope == 1) ? $socialite_user->email : $socialite_user->name . '#' . $socialite_user->user['discriminator'],
            'connector_name' => $socialite_user->nickname,
        ]);

        // invite the user to the guild using both nickname and roles
        $client->sendCall('PUT', '/guilds/{guild.id}/members/{user.id}', [
            'guild.id'     => $client->getGuildId(),
            'user.id'      => $socialite_user->id,
            'nick'         => Str::limit($driver_user->buildConnectorNickname(), Helper::NICKNAME_LENGTH_LIMIT, ''),
            'roles'        => $driver_user->allowedSets(),
            'access_token' => $socialite_user->token,
        ]);

        event(new EventLogger('discord', 'notice', 'registration',
            sprintf('User %s (%d) has been registered with ID %s and UID %s',
                $driver_user->connector_name, $driver_user->user_id, $driver_user->connector_id, $driver_user->unique_id)));

        // send the user to the guild
        return redirect()->to(sprintf('https://discord.com/channels/%s', $client->getGuildId()));
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\IClient $client
     * @param \Warlof\Seat\Connector\Models\User $old_identity
     * @throws \Seat\Services\Exceptions\SettingException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function revokeOldIdentity(IClient $client, User $old_identity)
    {
        try {
            // revoke access from old Identity
            $client->sendCall('PATCH', '/guilds/{guild.id}/members/{user.id}', [
                'guild.id' => $client->getGuildId(),
                'user.id' => $old_identity->connector_id,
                'nick' => Str::limit($old_identity->buildConnectorNickname(), Helper::NICKNAME_LENGTH_LIMIT, ''),
                'roles' => [],
            ]);

            // log action
            event(new EventLogger('discord', 'warning', 'registration',
                sprintf('User %s (%d) has been uncoupled from ID %s and UID %s',
                    $old_identity->connector_name, $old_identity->user_id, $old_identity->connector_id, $old_identity->unique_id)));
        } catch (ClientException $e) {
            logger()->error(sprintf('[seat-connector][discord] %s', $e->getMessage()));

            $body = $e->hasResponse() ? $e->getResponse()->getBody() : '{"code": 0}';
            $error = json_decode($body);

            if ($error->code == 10004) {
                event(new EventLogger('discord', 'warning', 'registration',
                    sprintf('User %s (%d) has been uncoupled from ID %s and UID %s',
                        $old_identity->connector_name, $old_identity->user_id, $old_identity->connector_id, $old_identity->unique_id)));

                return;
            }

            throw $e;
        }
    }
}
