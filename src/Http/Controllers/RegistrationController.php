<?php
/**
 * This file is part of SeAT Discord Connector.
 *
 * Copyright (C) 2019  Warlof Tutsimo <loic.leuilliot@gmail.com>
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

use Laravel\Socialite\Facades\Socialite;
use Seat\Web\Http\Controllers\Controller;
use SocialiteProviders\Manager\Config;
use Warlof\Seat\Connector\Drivers\Discord\Driver\DiscordClient;
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

        $redirect_uri = route('seat-connector.drivers.discord.registration.callback');

        $config = new Config($settings->client_id, $settings->client_secret, $redirect_uri);

        return Socialite::driver('discord')->setConfig($config)->setScopes(self::SCOPES)->redirect();
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Warlof\Seat\Connector\Exceptions\DriverSettingsException
     * @throws \Seat\Services\Exceptions\SettingException
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
        $driver_user = User::updateOrCreate([
            'connector_type' => 'discord',
            'connector_id'   => $socialite_user->id,
        ], [
            'connector_name' => $socialite_user->nickname,
            'group_id'       => auth()->user()->group_id,
            'unique_id'      => $socialite_user->email,
        ]);

        // invite the user to the guild using both nickname and roles
        $client->sendCall('PUT', '/guilds/{guild.id}/members/{user.id}', [
            'guild.id'     => $client->getGuildId(),
            'user.id'      => $socialite_user->id,
            'nick'         => $driver_user->buildConnectorNickname(),
            'roles'        => $driver_user->allowedSets(),
            'access_token' => $socialite_user->token,
        ]);

        // send the user to the guild
        return redirect()->to(sprintf('https://discordapp.com/channels/%s', $client->getGuildId()));
    }
}
