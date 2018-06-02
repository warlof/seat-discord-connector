<?php
/**
 * User: Warlof Tutsimo <loic.leuilliot@gmail.com>
 * Date: 02/06/2018
 * Time: 19:52
 */

namespace Warlof\Seat\Connector\Discord\Jobs;


use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Command\Exception\CommandClientException;
use RestCord\DiscordClient;
use Warlof\Seat\Connector\Discord\Exceptions\DiscordApiException;
use Warlof\Seat\Connector\Discord\Models\DiscordLog;
use Warlof\Seat\Connector\Discord\Models\DiscordUser;

/**
 * Class Invite
 * @package Warlof\Seat\Connector\Discord\Jobs
 */
class Invite extends DiscordJobBase
{
    const SCOPES = [
        'identify',
        'guilds.join',
    ];

    /**
     * @var array
     */
    protected $tags = ['sync', 'invite'];

    /**
     * @var DiscordUser
     */
    private $discord_user;

    /**
     * Invite constructor.
     * @param DiscordUser $discord_user
     */
    public function __construct(DiscordUser $discord_user)
    {
        $this->discord_user = $discord_user;
    }

    /**
     * @throws DiscordApiException
     * @throws \Seat\Services\Exceptions\SettingException
     */
    public function handle()
    {
        $this->inviteUserIntoGuild();

        DiscordLog::create([
            'event' => 'binding',
            'message' => sprintf('User %s has been successfully invited to the server.',
                $this->discord_user->name),
        ]);
    }

    private function inviteUserIntoGuild()
    {
        $driver = new DiscordClient([
            'tokenType' => 'Bot',
            'token'     => setting('warlof.discord-connector.credentials.bot_token', true),
        ]);

        $new_nickname = optional($this->discord_user->group->main_character)->name;

        $user = $driver->guild->addGuildMember([
            'user.id'  => $this->discord_user->discord_id,
            'guild.id' => intval(setting('warlof.discord-connector.credentials.guild_id', true)),
            'nick'     => ! is_null($new_nickname) ? $new_nickname : $this->discord_user->nick,
            'access_token' => $this->getAccessToken(),
        ]);

        if (! is_null($user->nick)) {
            $this->discord_user->nick = $user->nick;
            $this->discord_user->save();
        }
    }

    /**
     * @return string
     * @throws \Seat\Services\Exceptions\SettingException
     */
    private function getAccessToken()
    {
        $current = carbon()->setTimezone('UTC')->subMinute();

        if ($current->lte($this->discord_user->expires_at))
            return $this->discord_user->access_token;

        return $this->renewAccessToken();
    }

    /**
     * Renew the access token attached to the Discord User
     *
     * @return string
     * @throws \Seat\Services\Exceptions\SettingException
     */
    private function renewAccessToken()
    {
        $payload = [
            'client_id'     => setting('warlof.discord-connector.credentials.client_id', true),
            'client_secret' => setting('warlof.discord-connector.credentials.client_secret', true),
            'grant_type'    => 'refresh_token',
            'refresh_token' => $this->discord_user->refresh_token,
            'redirect_uri'  => route('discord-connector.server.callback'),
            'scope'         => implode(' ', self::SCOPES),
        ];

        $request = (new Client())->request('POST', 'https://discordapp.com/api/oauth2/token', [
            'form_params' => $payload,
        ]);

        $response = json_decode($request->getBody(), true);

        if (is_null($response))
            throw new Exception('response from Discord was empty.');

        $credentials = array_merge($response, [
            'expires_at' => carbon(array_first($request->getHeader('Date')))->addSeconds($response['expires_in']),
        ]);

        $this->discord_user->access_token = $credentials['access_token'];
        $this->discord_user->expires_at   = $credentials['expires_at'];
        $this->discord_user->save();

        return $credentials['access_token'];
    }

}
