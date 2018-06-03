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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Artisan;
use Parsedown;
use Seat\Web\Http\Controllers\Controller;

/**
 * Class DiscordSettingsController
 * @package Warlof\Seat\Connector\Discord\Http\Controllers
 */
class DiscordSettingsController extends Controller
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getConfiguration()
    {
        $changelog = $this->getChangelog();

        return view('discord-connector::configuration', compact('changelog'));
    }

    /**
     * @param $command_name
     * @return \Illuminate\Http\RedirectResponse
     */
    public function getSubmitJob($command_name)
    {
        $accepted_commands = [
            'discord:role:sync',
            'discord:user:sync',
            'discord:user:terminator',
            'discord:logs:clear'
        ];

        if (! in_array($command_name, $accepted_commands)) {
            abort(400);
        }

        Artisan::call($command_name);

        return redirect()->back()
            ->with('success', 'The command has been run.');
    }

    /**
     * @return string
     */
    private function getChangelog() : string
    {
        try {
            $response = (new Client())
                ->request('GET', "https://raw.githubusercontent.com/warlof/seat-discord-connector/master/CHANGELOG.md");

            if ($response->getStatusCode() != 200) {
                return 'Error while fetching changelog';
            }

            $parser = new Parsedown();
            return $parser->parse($response->getBody());
        } catch (RequestException $e) {
            return 'Error while fetching changelog';
        }
    }
}
