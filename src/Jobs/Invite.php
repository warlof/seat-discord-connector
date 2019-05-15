<?php
/**
 * This file is part of discord-connector and provides user synchronization between both SeAT and a Discord Guild
 *
 * Copyright (C) 2016, 2017, 2018  Loïc Leuilliot <loic.leuilliot@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Warlof\Seat\Connector\Discord\Jobs;

use GuzzleHttp\Client;
use Seat\Eveapi\Models\Corporation\CorporationInfo;
use UnexpectedValueException;
use Warlof\Seat\Connector\Discord\Exceptions\DiscordSettingException;
use Warlof\Seat\Connector\Discord\Helpers\Helper;
use Warlof\Seat\Connector\Discord\Models\DiscordLog;
use Warlof\Seat\Connector\Discord\Models\DiscordUser;

/**
 * Class Invite
 * @package Warlof\Seat\Connector\Discord\Jobs
 */
class Invite extends DiscordJobBase
{
    const SCOPES = [
        'identify',
        'guilds.join',
    ];

    /**
     * @var array
     */
    protected $tags = ['sync', 'invite'];

    /**
     * @var DiscordUser
     */
    private $discord_user;

    /**
     * Invite constructor.
     * @param DiscordUser $discord_user
     */
    public function __construct(DiscordUser $discord_user)
    {
        $this->discord_user = $discord_user;
    }

    /**
     * @throws DiscordSettingException
     * @throws \Seat\Services\Exceptions\SettingException
     */
    public function handle()
    {
        if (is_null(setting('warlof.discord-connector.credentials.bot_token', true)))
            throw new DiscordSettingException();

        if (is_null(setting('warlof.discord-connector.credentials.guild_id', true)))
            throw new DiscordSettingException();

        if (is_null(setting('warlof.discord-connector.credentials.client_id', true)))
            throw new DiscordSettingException();

        if (is_null(setting('warlof.discord-connector.credentials.client_secret', true)))
            throw new DiscordSettingException();

        $this->inviteUserIntoGuild();

        DiscordLog::create([
            'event' => 'binding',
            'message' => sprintf('User %s has been successfully invited to the server.',
                $this->discord_user->name),
        ]);
    }

    /**
     * @throws \Seat\Services\Exceptions\SettingException
     */
    private function inviteUserIntoGuild()
    {

        $corporation = CorporationInfo::find($this->discord_user->group->main_character->corporation_id);
        $nickname = $this->discord_user->group->main_character->name;
        $expected_nickname = $nickname;

        if (setting('warlof.discord-connector.ticker', true))
            $expected_nickname = sprintf('[%s] %s', $corporation->ticker, $nickname);

        $roles = Helper::allowedRoles($this->discord_user);

        $options = [
            'user.id' => $this->discord_user->discord_id,
            'guild.id' => intval(setting('warlof.discord-connector.credentials.guild_id', true)),
            'nick' => $expected_nickname,
            'roles' => $roles,
        ];

        try {
            $guild_member = app('discord')->guild->getGuildMember([
                'guild.id' => intval(setting('warlof.discord-connector.credentials.guild_id', true)),
                'user.id' => $this->discord_user->discord_id,
            ]);
            app('discord')->guild->modifyGuildMember($options);
        } catch (\Exception $e) {
            $options['access_token'] = $this->getAccessToken();
            $guild_member = app('discord')->guild->addGuildMember($options);
        }

        if (property_exists($guild_member, 'nick') && ! is_null($guild_member->nick)) {
            $this->discord_user->nick = $guild_member->nick;
            $this->discord_user->save();
        }
    }

    /**
     * @return string
     * @throws \Seat\Services\Exceptions\SettingException
     */
    private function getAccessToken()
    {
        $current = carbon()->setTimezone('UTC')->subMinute();

        if ($current->lte($this->discord_user->expires_at))
            return $this->discord_user->access_token;

        return $this->renewAccessToken();
    }

    /**
     * Renew the access token attached to the Discord User
     *
     * @return string
     * @throws \Seat\Services\Exceptions\SettingException
     */
    private function renewAccessToken()
    {
        $payload = [
            'client_id'     => setting('warlof.discord-connector.credentials.client_id', true),
            'client_secret' => setting('warlof.discord-connector.credentials.client_secret', true),
            'grant_type'    => 'refresh_token',
            'refresh_token' => $this->discord_user->refresh_token,
            'redirect_uri'  => route('discord-connector.server.callback'),
            'scope'         => implode(' ', self::SCOPES),
        ];

        $request = (new Client())->request('POST', 'https://discordapp.com/api/oauth2/token', [
            'form_params' => $payload,
        ]);

        $response = json_decode($request->getBody(), true);

        if (is_null($response))
            throw new UnexpectedValueException('response from Discord was empty.');

        $credentials = array_merge($response, [
            'expires_at' => carbon(array_first($request->getHeader('Date')))->addSeconds($response['expires_in']),
        ]);

        $this->discord_user->access_token = $credentials['access_token'];
        $this->discord_user->expires_at   = $credentials['expires_at'];
        $this->discord_user->save();

        return $credentials['access_token'];
    }

}
