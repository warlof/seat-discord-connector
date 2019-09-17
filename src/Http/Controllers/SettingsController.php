<?php
/**
 * This file is part of SeAT Discord Connector.
 *
 * Copyright (C) 2019  Warlof Tutsimo <loic.leuilliot@gmail.com>
 *
 * SeAT Discord Connector  is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * SeAT Discord Connector is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Warlof\Seat\Connector\Drivers\Discord\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Seat\Web\Http\Controllers\Controller;
use SocialiteProviders\Manager\Config;
use Warlof\Seat\Connector\Drivers\Discord\Helpers\Helper;

/**
 * Class SettingsController.
 *
 * @package Warlof\Seat\Connector\Drivers\Discord\Http\Controllers
 */
class SettingsController extends Controller
{
    const SCOPES = [
        'bot', 'identify', 'guilds.join',
    ];

    const BOT_PERMISSIONS = [
        'MANAGE_ROLES'          => 0x10000000,
        'KICK_MEMBERS'          => 0x00000002,
        'BAN_MEMBERS'           => 0x00000004,
        'CREATE_INSTANT_INVITE' => 0x00000001,
        'CHANGE_NICKNAME'       => 0x04000000,
        'MANAGE_NICKNAMES'      => 0x08000000,
    ];

    /**
     * @param \Illuminate\Http\Request $request
     * @return mixed
     * @throws \Seat\Services\Exceptions\SettingException
     */
    public function store(Request $request)
    {
        $request->validate([
            'client_id'     => 'required|string',
            'client_secret' => 'required|string',
            'bot_token'     => 'required|string',
        ]);

        $settings = (object) [
            'client_id'     => $request->input('client_id'),
            'client_secret' => $request->input('client_secret'),
            'bot_token'     => $request->input('bot_token'),
        ];

        setting(['seat-connector.drivers.discord', $settings], true);

        $redirect_uri = route('seat-connector.drivers.discord.settings.callback');

        $config = new Config($settings->client_id, $settings->client_secret, $redirect_uri);

        return Socialite::driver('discord')
            ->with([
                'permissions' => Helper::arrayBitwiseOr(self::BOT_PERMISSIONS),
            ])->setConfig($config)
            ->setScopes(self::SCOPES)
            ->redirect();
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Seat\Services\Exceptions\SettingException
     */
    public function handleProviderCallback()
    {
        $settings = setting('seat-connector.drivers.discord', true);

        $redirect_uri = route('seat-connector.drivers.discord.settings.callback');

        $config = new Config($settings->client_id, $settings->client_secret, $redirect_uri);

        $socialite_user = Socialite::driver('discord')->setConfig($config)->user();

        $settings->guild_id = $socialite_user->accessTokenResponseBody['guild']['id'];
        $settings->owner_id = $socialite_user->accessTokenResponseBody['guild']['owner_id'];

        setting(['seat-connector.drivers.discord', $settings], true);

        return redirect()->route('seat-connector.settings')
            ->with('success', 'Discord settings has successfully been updated.');
    }
}
