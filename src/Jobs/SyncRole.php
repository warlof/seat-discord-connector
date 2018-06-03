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

use RestCord\DiscordClient;
use Warlof\Seat\Connector\Discord\Exceptions\DiscordSettingException;
use Warlof\Seat\Connector\Discord\Models\DiscordRole;

/**
 * Class SyncRole
 * @package Warlof\Seat\Connector\Discord\Jobs
 */
class SyncRole extends DiscordJobBase
{

    /**
     * @var array
     */
    protected $tags = ['sync', 'conversations'];

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

        $driver = new DiscordClient([
            'tokenType' => 'Bot',
            'token' => setting('warlof.discord-connector.credentials.bot_token', true),
        ]);

        $discord_roles = $driver->guild->getGuildRoles([
            'guild.id' => intval(setting('warlof.discord-connector.credentials.guild_id', true)),
        ]);

        $conversations_buffer = [];

        foreach ($discord_roles as $role) {

            // skip managed roles as we are not able to use them
            if ($role->managed || $role->name == '@everyone')
                continue;

            $conversations_buffer[] = $role->id;

            DiscordRole::updateOrCreate([
                    'id' => $role->id,
                ], [
                    'name'       => $role->name,
                ]);

        }

        DiscordRole::whereNotIn('id', $conversations_buffer)->delete();
    }

}
