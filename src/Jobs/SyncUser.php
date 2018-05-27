<?php
/**
 * This file is part of slackbot and provide user synchronization between both SeAT and a Slack Team
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
use Illuminate\Support\Collection;
use RestCord\DiscordClient;
use Seat\Web\Models\Group;
use Seat\Web\Models\User;
use Warlof\Seat\Connector\Discord\Models\DiscordLog;
use Warlof\Seat\Connector\Discord\Models\DiscordUser;

class SyncUser extends DiscordJobBase {

    /**
     * @var array
     */
    protected $tags = ['sync', 'users'];

    /**
     * Handle user mapping between both SeAT and Discord
     */
    public function handle()
    {
        // excluding mapped group and group without email address
        $groups = Group::select('id')->whereNotIn('id', function ($sub_query) {
            $sub_query->select('group_id')->from('warlof_discord_connector_users');
        })->get()->filter(function ($group) {
            return ! empty($group->email);
        });

        $users = User::whereIn('id', $groups->pluck('main_character_id')->toArray())->get();

        $this->bindingDiscordUser($users);
    }

    /**
     * @param Collection $users
     */
    private function bindingDiscordUser(Collection $users)
    {
        // TODO : move into container
        // TODO : bind to guild
        // TODO : use OAuth credentials instead bot one
        $token = setting('warlof.discord-connector.credentials.token', true);

        if (carbon()->setTimezone('UTC')->gte(carbon($token->expires))) {
            $payload = [
                'client_id' => setting('warlof.discord-connector.credentials.client_id', true),
                'client_secret' => setting('warlof.discord-connector.credentials.client_secret', true),
                'refresh_token' => $token->refresh,
                'grant_type' => 'refresh_token',
                'scope' => implode(OAuthController::SCOPES, ' '),
            ];

            $request = (new Client())->request('POST', 'https://discordapp.com/api/oauth2/token', [
                'form-params' => $payload,
            ]);

            $response = json_decode($request->getBody());
            $token->access = $response->access_token;
            $token->expires = carbon(array_first($request->getHeader('Date')))->addSeconds($response->expires_in)->toDateTimeString();

            setting(['warlof.discord-connector.credentials.token', $token], true);
        }

        $token = setting('warlof.discord-connector.credentials.token', true);

        $driver = new DiscordClient([
            'tokenType' => 'OAuth',
            'token' => $token->access,
        ]);
        $discord_members = $driver->guild->listGuildMembers(['guild.id' => intval(setting('warlof.discord-connector.credentials.guild_id', true))]);

        logger()->debug('Discord SyncUser listGuildMembers', ['members' => $discord_members]);

        foreach ($discord_members as $discord_member) {

            if (! is_null($discord_member->user->email))
                continue;

            $user = $users->where('email', $discord_member->user->email)->first();

            if (is_null($user))
                continue;

            DiscordUser::create([
                'group_id' => $user->group_id,
                'discord_id' => $discord_member->user->id,
                'name' => $discord_member->nick ?: $discord_member->user->username,
            ]);

            DiscordLog::create([
                'event' => 'binding',
                'message' => sprintf('User %s (%s) has been successfully bind to %s',
                    $user->name,
                    $user->email,
                    $discord_member->nick ?: $discord_member->user->username),
            ]);

        }
    }

}
