<?php
/**
 * This file is part of discord-connector and provide user synchronization between both SeAT and a Discord Guild
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

namespace Warlof\Seat\Connector\Discord\Http\Controllers\Services;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Seat\Web\Http\Controllers\Controller;
use Warlof\Seat\Connector\Discord\Http\Validation\ValidateOAuth;

class OAuthController extends Controller
{
    /**
     * Scopes used in OAuth flow with Discord
     */
    const SCOPES = [
        'bot', 'email'
    ];

    /**
     * @param ValidateOAuth $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postConfiguration(ValidateOAuth $request)
    {
        $state = time();

        if (
        (setting('warlof.discord-connector.credentials.client_id', true) == $request->input('discord-configuration-client')) &&
        (setting('warlof.discord-connector.credentials.client_secret', true) == $request->input('discord-configuration-secret')) &&
        ($request->input('discord-configuration-verification') != '')) {
            setting([
                'warlof.discord-connector.credentials.verification_token',
                $request->input('discord-configuration-verification')
            ], true);
            return redirect()->back()->with('success', 'Change has been successfully applied.');
        }

        // store data into the session until OAuth confirmation
        session()->put('warlof.discord-connector.credentials', [
            'client_id' => $request->input('discord-configuration-client'),
            'client_secret' => $request->input('discord-configuration-secret'),
            'state' => $state
        ]);

        return redirect($this->oAuthAuthorization($request->input('discord-configuration-client'), $state));
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function callback(Request $request)
    {
        // get back pending OAuth credentials validation from session
        $oauthCredentials = session()->get('warlof.discord-connector.credentials');

        session()->forget('warlof.discord-connector.credentials');

        // ensure request is legitimate
        if ($oauthCredentials['state'] != $request->input('state')) {
            redirect()->back()
                ->with('error', 'An error occurred while getting back the token. Returned state value is wrong. ' .
                    'In order to prevent any security issue, we stopped transaction.');
        }

        // validating Discord credentials
        try {

            $payload = [
                'client_id' => $oauthCredentials['client_id'],
                'client_secret' => $oauthCredentials['client_secret'],
                'grant_type' => 'authorization_code',
                'code' => $request->input('code'),
                'scopes' => implode(self::SCOPES, ' '),
            ];

            $response = (new Client())->request('POST', 'https://discordapp.com/api/oauth2/token', [
                'form_params' => $payload
            ]);

            if ($response->getStatusCode() != 200)
                throw new Exception($response->getBody(), $response->getStatusCode());

            $result = json_decode($response->getBody(), true);

            if ($result == null)
                throw new Exception("response from Discord was empty.");

            setting(['warlof.discord-connector.credentials.client_id', $oauthCredentials['client_id']], true);
            setting(['warlof.discord-connector.credentials.client_secret', $oauthCredentials['client_secret']], true);
            setting(['warlof.discord-connector.credentials.token', [
                'access' => $result['access_token'],
                'refresh' => $result['refresh_token'],
                'expires' => carbon(array_first($response->getHeader('Date')))->addSeconds($result['expires_in'])->toDateTimeString(),
            ]], true);
            setting(['warlof.discord-connector.credentials.guild_id', $request->input('guild_id')], true);

        } catch (Exception $e) {
            return redirect()->route('discord-connector.configuration')
                ->with('error', 'An error occurred while trying to confirm OAuth credentials with Discord. ' .
                $e->getMessage());
        }

        return redirect()->route('discord-connector.configuration')
            ->with('success', 'The bot credentials has been set.');
    }

    private function oAuthAuthorization($clientId, $state)
    {
        $baseUri = 'https://discordapp.com/api/oauth2/authorize?';

        return $baseUri . http_build_query([
            'response_type' => 'code',
            'client_id'     => $clientId,
            'permissions'   => 402653187,
            'scope'         => implode(' ', self::SCOPES),
            'state'         => $state
        ]);
    }
}
