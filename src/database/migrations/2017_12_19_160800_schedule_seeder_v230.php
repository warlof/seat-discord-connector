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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Seat\Services\Models\Schedule;

/**
 * Class ScheduleSeederV230
 */
class ScheduleSeederV230 extends Migration
{
    /**
     * @var array
     */
    protected $schedule = [
        [
            'command'           => 'discord:user:policy',
            'expression'        => '*,30 * * * *',
            'allow_overlap'     => false,
            'allow_maintenance' => false,
            'ping_before'       => 'https://discordapp.com',
            'ping_after'        => null,
        ],
        [
            'command'           => 'discord:role:sync',
            'expression'        => '0 * * * *',
            'allow_overlap'     => false,
            'allow_maintenance' => false,
            'ping_before'       => 'https://discordapp.com',
            'ping_after'        => null,
        ]
    ];

    /**
     * Seeding schedule table with cron tasks
     */
    public function up()
    {
        foreach ($this->schedule as $job) {
            $existing = Schedule::where('command', $job['command'])
                          ->first();

            if ($existing) {
                $existing->update([
                    'expression' => $job['expression'],
                ]);
            }

            if (!$existing) {
                DB::table('schedules')->insert($job);
            }
        }
    }
}