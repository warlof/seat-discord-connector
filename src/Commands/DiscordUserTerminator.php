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
namespace Warlof\Seat\Connector\Discord\Commands;

use Illuminate\Console\Command;
use Warlof\Seat\Connector\Discord\Jobs\MemberDispatcher;

class DiscordUserTerminator extends Command
{

    /**
     * @var string
     */
    protected $signature = 'discord:user:terminator';

    /**
     * @var string
     */
    protected $description = 'Fire a job which will kick all users from every roles on Discord.';

    /**
     * Execute the console command
     */
    public function handle()
    {
        // queue the job and inform user
        dispatch(new MemberDispatcher(true));

        $this->info('A synchronization job has been queued in order to update discord/seat user relation.');
    }

}
