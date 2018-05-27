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
use RestCord\DiscordClient;
use Warlof\Seat\Connector\Discord\Http\Controllers\Services\OAuthController;
use Warlof\Seat\Connector\Discord\Models\DiscordRole;

class SyncRole extends DiscordJobBase {

    /**
     * @var array
     */
    protected $tags = ['sync', 'conversations'];

    /**
     * @throws \Warlof\Seat\Connector\Discord\Exceptions\DiscordSettingException
     */
    public function handle()
    {
        // TODO : move into container
        // TODO : bind to guild
        // TODO : use OAuth credentials instead bot one
        $token = setting('warlof.discord-connector.credentials.token', true);

        if (carbon()->setTimezone('UTC')->gte(carbon($token['expires']))) {
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
        $discord_roles = $driver->guild->getGuildRoles(['guild.id' => intval(setting('warlof.discord-connector.credentials.guild_id', true))]);

        $conversations_buffer = [];

        foreach ($discord_roles as $role) {

            $conversations_buffer[] = $role->id;
            DiscordRole::updateOrCreate(
                [
                    'id' => $role->id,
                ],
                [
                    'name'       => $role->name,
                ]);

        }

        DiscordRole::whereNotIn('id', $conversations_buffer)->delete();
    }

}
