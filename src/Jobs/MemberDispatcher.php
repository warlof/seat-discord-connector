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

/**
 * Class MemberDispatcher
 * @package Warlof\Seat\Connector\Discord\Jobs
 */
class MemberDispatcher extends DiscordJobBase
{

    /**
     * @var array
     */
    protected $tags = ['dispatcher'];

    /**
     * @var bool
     */
    private $terminator;

    /**
     * ConversationDispatcher constructor.
     * @param bool $terminator Determine if the dispatcher must run a massive kick
     */
    public function __construct(bool $terminator = false)
    {
        $this->terminator = $terminator;

        // in case terminator mode is active, append terminator to tags
        if ($this->terminator)
            array_push($this->tags, 'terminator');
    }

    /**
     * @throws \Seat\Services\Exceptions\SettingException
     */
    public function handle() {

        $driver = new DiscordClient([
            'tokenType' => 'Bot',
            'token'     => setting('warlof.discord-connector.credentials.bot_token', true),
        ]);

        $members = $driver->guild->listGuildMembers([
            'guild.id' => intval(setting('warlof.discord-connector.credentials.guild_id', true)),
            'limit'    => 1000,
        ]);

        foreach ($members as $member) {

            $job = new MemberOrchestrator($member);

            if ($this->terminator)
                $job->setTerminatorFlag();

            dispatch($job);

        }
    }
}
