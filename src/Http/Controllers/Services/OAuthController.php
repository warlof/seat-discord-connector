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
use RestCord\DiscordClient;
use Seat\Web\Http\Controllers\Controller;
use Warlof\Seat\Connector\Discord\Caches\RedisRateLimitProvider;
use Warlof\Seat\Connector\Discord\Helpers\Helper;
use Warlof\Seat\Connector\Discord\Http\Validation\ValidateOAuth;

/**
 * Class OAuthController
 * @package Warlof\Seat\Connector\Discord\Http\Controllers\Services
 */
class OAuthController extends Controller
{
    /**
     * Scopes used in OAuth flow with Discord
     */
    const SCOPES = [
        'bot', 'guilds.join',
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
     * @param ValidateOAuth $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postConfiguration(ValidateOAuth $request)
    {
        $state = time();

        // store data into the session until OAuth confirmation
        session(['warlof.discord-connector.credentials' => [
            'state'         => $state,
            'client_id'     => $request->input('discord-configuration-client'),
            'client_secret' => $request->input('discord-configuration-secret'),
            'bot_token'     => $request->input('discord-configuration-bot'),
        ]]);

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
        $credentials = $request->session()->get('warlof.discord-connector.credentials');

        $request->session()->forget('warlof.discord-connector.credentials');

        if (! $this->isValidCallback($credentials))
            return redirect()->route('home')
                ->with('error', 'An error occurred while processing the request. ' .
                    'For some reason, your session was not met system requirement.');

        // ensure request is legitimate
        if ($credentials['state'] != $request->input('state')) {
            return redirect()->back()
                ->with('error', 'An error occurred while getting back the token. Returned state value is wrong. ' .
                    'In order to prevent any security issue, we stopped transaction.');
        }

        // validating Discord credentials
        try {

            $token = $this->exchangeToken($credentials['client_id'], $credentials['client_secret'],
                $request->input('code'));

            setting(['warlof.discord-connector.credentials.client_id', $credentials['client_id']], true);
            setting(['warlof.discord-connector.credentials.client_secret', $credentials['client_secret']], true);
            setting(['warlof.discord-connector.credentials.token', [
                'access'  => $token['access_token'],
                'refresh' => $token['refresh_token'],
                'expires' => carbon($token['request_date'])->addSeconds($token['expires_in'])->toDateTimeString(),
                'scope'  => $token['scope'],
            ]], true);
            setting(['warlof.discord-connector.credentials.bot_token', $credentials['bot_token']], true);
            setting(['warlof.discord-connector.credentials.guild_id', $request->input('guild_id')], true);

            // update Discord container
            app()->singleton('discord', function () {
                return new DiscordClient([
                    'tokenType'         => 'Bot',
                    'token'             => setting('warlof.discord-connector.credentials.bot_token', true),
                    'rateLimitProvider' => new RedisRateLimitProvider(),
                ]);
            });

        } catch (Exception $e) {
            return redirect()->route('discord-connector.configuration')
                ->with('error', 'An error occurred while trying to confirm OAuth credentials with Discord. ' .
                $e->getMessage());
        }

        return redirect()->route('discord-connector.configuration')
            ->with('success', 'The bot credentials has been set.');
    }

    /**
     * Return an authorization uri with presets scopes
     *
     * @param $client_id
     * @param $state
     * @return string
     */
    private function oAuthAuthorization($client_id, $state)
    {
        $base_uri = 'https://discordapp.com/api/oauth2/authorize?';

        $permissions = Helper::arrayBitwiseOr(self::BOT_PERMISSIONS);

        return $base_uri . http_build_query([
            'response_type' => 'code',
            'client_id'     => $client_id,
            'permissions'   => $permissions,
            'scope'         => implode(' ', self::SCOPES),
            'state'         => $state,
            'redirect_uri'  => route('discord-connector.oauth.callback'),
        ]);
    }

    /**
     * Exchange an Authorization Code with an Access Token
     *
     * @param string $client_id
     * @param string $client_secret
     * @param string $code
     * @return array
     * @throws Exception
     */
    private function exchangeToken(string $client_id, string $client_secret, string $code)
    {
        $payload = [
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => route('discord-connector.oauth.callback'),
            'scope'         => implode(self::SCOPES, ' '),
        ];

        $request = (new Client())->request('POST', 'https://discordapp.com/api/oauth2/token', [
            'form_params' => $payload
        ]);

        $response = json_decode($request->getBody(), true);

        if (is_null($response))
            throw new Exception("response from Discord was empty.");

        return array_merge($response, [
            'request_date' => array_first($request->getHeader('Date')),
        ]);
    }

    /**
     * Ensure an array is containing all expected values in a valid callback session
     *
     * @param $session_content
     * @return bool
     */
    private function isValidCallback($session_content)
    {
        $expected_array_keys = ['state', 'client_id', 'client_secret', 'bot_token'];
        $i = count($expected_array_keys);

        if (is_null($session_content))
            return false;

        if (! is_array($session_content))
            return false;

        while ($i > 0) {
            $i--;

            if (! array_key_exists($expected_array_keys[$i], $session_content))
                return false;
        }

        return true;
    }
}
