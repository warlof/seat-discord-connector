<?php
/**
 * User: Warlof Tutsimo <loic.leuilliot@gmail.com>
 * Date: 02/06/2018
 * Time: 17:34
 */

namespace Warlof\Seat\Connector\Discord\Http\Controllers\Services;


use Exception;
use GuzzleHttp\Client;
use RestCord\DiscordClient;
use RestCord\Model\User\User;
use Seat\Web\Http\Controllers\Controller;
use Warlof\Seat\Connector\Discord\Jobs\Invite;
use Warlof\Seat\Connector\Discord\Models\DiscordUser;

class ServerController extends Controller
{

    const SCOPES = [
        'identify',
        'guilds.join',
    ];

    public function join()
    {
        $state = time();

        $client_id = setting('warlof.discord-connector.credentials.client_id', true);

        if (is_null($client_id))
            return redirect()->route('home')
                ->with('error', 'System administrator did not end the connector setup.');

        session(['warlof.discord-connector.user.state' => $state]);

        return redirect($this->oAuthAuthorization($client_id, $state));
    }

    public function callback()
    {
        $state = request()->session()->get('warlof.discord-connector.user.state');

        request()->session()->forget('warlof.discord-connector.user.state');

        if ($state != intval(request()->input('state')))
            return redirect()->route('home')
                ->with('error', 'An error occurred while getting back the token. Returned state value is wrong. ' .
                                'In order to prevent any security issue, we stopped transaction.');

        try {
            $credentials = $this->exchangingToken(request()->input('code'));

            if (! in_array('identify', explode(' ', $credentials['scope'])))
                return redirect()->route('home')
                    ->with('error', 'We were not able to retrieve your user information. Did you alter authorization ?');

            $user_information = $this->retrievingUserInformation($credentials['access_token']);

            $this->bindingUser($user_information, $credentials);

        } catch (Exception $e) {
            return redirect()->route('home')
                ->with('error', 'An error occurred while exchanging credentials with Discord. ' . $e->getMessage());
        }

        return redirect()->route('home')
            ->with('success', 'Your account has been bind to SeAT. You will get your invitation shortly.');
    }

    /**
     * Getting a OAuth Authorization query with presets scopes
     *
     * @param string $client_id
     * @param int $state
     * @return string
     */
    private function oAuthAuthorization(string $client_id, int $state)
    {
        $base_uri = 'https://discordapp.com/api/oauth2/authorize?';

        return $base_uri . http_build_query([
            'client_id'     => $client_id,
            'response_type' => 'code',
            'state'         => $state,
            'redirect_uri'  => route('discord-connector.server.callback'),
            'scope'         => implode(' ', self::SCOPES),
        ]);
    }

    /**
     * Exchanging an authorization code to an access token
     *
     * @param string $code
     * @return array
     * @throws \Seat\Services\Exceptions\SettingException
     */
    private function exchangingToken(string $code) : array
    {
        $payload = [
            'client_id' => setting('warlof.discord-connector.credentials.client_id', true),
            'client_secret' => setting('warlof.discord-connector.credentials.client_secret', true),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri'  => route('discord-connector.server.callback'),
            'scope' => implode(' ', self::SCOPES),
        ];

        $request = (new Client())->request('POST', 'https://discordapp.com/api/oauth2/token', [
            'form_params' => $payload,
        ]);

        $response = json_decode($request->getBody(), true);

        if (is_null($response))
            throw new Exception('response from Discord was empty.');

        return array_merge($response, [
            'expires_at' => carbon(array_first($request->getHeader('Date')))->addSeconds($response['expires_in']),
        ]);
    }

    /**
     * Return information related user attached to the token
     *
     * @param string $access_token
     * @return User
     */
    private function retrievingUserInformation(string $access_token)
    {
        $driver = new DiscordClient(['token' => $access_token, 'tokenType' => 'OAuth']);

        return $driver->user->getCurrentUser([]);
    }

    /**
     * Create a new SeAT/Discord user association and queue an invitation job
     *
     * @param User $user
     */
    private function bindingUser(User $user, array $credentials)
    {
        // create a new binding between authenticated user and discord user.
        // in case a binding already exists, update credentials.
        $discord_user = DiscordUser::updateOrCreate([
            'group_id'   => auth()->user()->group_id,
        ], [
            'discord_id'    => $user->id,
            'nick'          => $user->username,
            'refresh_token' => $credentials['refresh_token'],
            'access_token'  => $credentials['access_token'],
            'expires_at'    => $credentials['expires_at'],
        ]);

        dispatch(new Invite($discord_user));
    }

}
