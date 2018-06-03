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

namespace Warlof\Seat\Connector\Discord\Http\Controllers;

use Seat\Web\Http\Controllers\Controller;
use Warlof\Seat\Connector\Discord\Models\DiscordLog;
use Yajra\Datatables\Facades\Datatables;

/**
 * Class DiscordLogsController
 * @package Warlof\Seat\Connector\Discord\Http\Controllers
 */
class DiscordLogsController extends Controller
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getLogs()
    {
        $log_count = DiscordLog::count();
        return view('discord-connector::logs.list', compact('log_count'));
    }

    /**
     * @return mixed
     */
    public function getJsonLogData()
    {
        $logs = DiscordLog::orderBy('created_at', 'desc')->get();

        return Datatables::of($logs)
            ->editColumn('created_at', function($row){
                return view('discord-connector::logs.partial.date', compact('row'));
            })
            ->make(true);
    }
}
