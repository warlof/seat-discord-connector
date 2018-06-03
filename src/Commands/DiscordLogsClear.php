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

namespace Warlof\Seat\Connector\Discord\Commands;

use Illuminate\Console\Command;
use Warlof\Seat\Connector\Discord\Models\DiscordLog;

/**
 * Class DiscordLogsClear
 * @package Warlof\Seat\Connector\Discord\Commands
 */
class DiscordLogsClear extends Command
{
    /**
     * @var string
     */
    protected $signature = 'discord:logs:clear';

    /**
     * @var string
     */
    protected $description = 'Clearing Discord logs';

    /**
     * DiscordLogsClear constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        DiscordLog::truncate();
    }
}
