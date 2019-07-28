<?php

namespace Warlof\Seat\Connector\Drivers\Discord\Driver;

use GuzzleHttp\Client;
use Warlof\Seat\Connector\Drivers\IClient;
use Warlof\Seat\Connector\Drivers\ISet;
use Warlof\Seat\Connector\Drivers\IUser;
use Warlof\Seat\Connector\Exceptions\DriverSettingsException;

/**
 * Class DiscordClient.
 *
 * @package Warlof\Seat\Connector\Drivers\Discord\Driver
 */
class DiscordClient implements IClient
{
    CONST BASE_URI = 'https://discordapp.com/api';

    CONST VERSION = 'v6';

    /**
     * @var \Warlof\Seat\Connector\Drivers\Discord\Driver\DiscordClient
     */
    private static $instance;

    /**
     * @var \GuzzleHttp\Client
     */
    private $client;

    /**
     * @var string
     */
    private $guild_id;

    /**
     * @var string
     */
    private $bot_token;

    /**
     * @var \Warlof\Seat\Connector\Drivers\IUser[]
     */
    private $members;

    /**
     * @var \Warlof\Seat\Connector\Drivers\ISet[]
     */
    private $roles;

    /**
     * DiscordClient constructor.
     *
     * @param array $parameters
     */
    private function __construct(array $parameters)
    {
        $this->guild_id  = $parameters['guild_id'];
        $this->bot_token = $parameters['bot_token'];

        $this->members = collect();
        $this->roles   = collect();
    }

    /**
     * @return \Warlof\Seat\Connector\Drivers\Discord\Driver\DiscordClient
     * @throws \Warlof\Seat\Connector\Exceptions\DriverSettingsException
     * @throws \Seat\Services\Exceptions\SettingException
     */
    public static function getInstance(): IClient
    {
        if (! isset(self::$instance)) {
            $settings  = setting('seat-connector.drivers.discord', true);

            if (is_null($settings) || ! is_object($settings))
                throw new DriverSettingsException('The Driver has not been configured yet.');

            if (! property_exists($settings, 'guild_id') || is_null($settings->guild_id) || $settings->guild_id == '')
                throw new DriverSettingsException('Parameter guild_id is missing.');

            if (! property_exists($settings, 'bot_token') || is_null($settings->bot_token) || $settings->bot_token == '')
                throw new DriverSettingsException('Parameter bot_token is missing.');

            self::$instance = new DiscordClient([
                'guild_id' => $settings->guild_id,
                'bot_token' => $settings->bot_token,
            ]);
        }

        return self::$instance;
    }

    /**
     * @return \Warlof\Seat\Connector\Drivers\IUser[]
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getUsers(): array
    {
        if ($this->members->isEmpty())
            $this->seedMembers();

        return $this->members->toArray();
    }

    /**
     * @param string $id
     * @return \Warlof\Seat\Connector\Drivers\IUser
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getUser(string $id): ?IUser
    {
        if ($this->members->isEmpty())
            $this->seedMembers();

        $member = $this->members->get($id);

        if (is_null($member)) {

            $member = $this->sendCall('GET', '/guilds/{guild.id}/members/{user.id}', [
                'guild.id' => $this->guild_id,
                'user.id'  => $id,
            ]);

            $member = new DiscordMember((array) $member);
        }

        return $member;
    }

    /**
     * @return \Warlof\Seat\Connector\Drivers\ISet[]
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Seat\Services\Exceptions\SettingException
     * @throws \Warlof\Seat\Connector\Exceptions\DriverSettingsException
     */
    public function getSets(): array
    {
        if ($this->roles->isEmpty())
            $this->seedRoles();

        return $this->roles->toArray();
    }

    /**
     * @param string $id
     * @return \Warlof\Seat\Connector\Drivers\ISet|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Seat\Services\Exceptions\SettingException
     * @throws \Warlof\Seat\Connector\Exceptions\DriverSettingsException
     */
    public function getSet(string $id): ?ISet
    {
        if ($this->roles->isEmpty())
            $this->seedRoles();

        return $this->roles->get($id);
    }

    /**
     * @return string
     */
    public function getGuildId(): string
    {
        return $this->guild_id;
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array $arguments
     * @return object
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function sendCall(string $method, string $endpoint, array $arguments = [])
    {
        $uri = ltrim($endpoint, '/');

        foreach ($arguments as $uri_parameter => $value) {
            if (strpos($uri, sprintf('{%s}', $uri_parameter)) === false)
                continue;

            $uri = str_replace(sprintf('{%s}', $uri_parameter), $value, $uri);
            array_pull($arguments, $uri_parameter);
        }

        if (is_null($this->client))
            $this->client = new Client([
                'base_uri' => sprintf('%s/%s', rtrim(self::BASE_URI, '/'), self::VERSION),
                'headers' => [
                    'Authorization' => sprintf('Bot %s', $this->bot_token),
                    'Content-Type'  => 'application/json',
                ],
            ]);

        if ($method == 'GET') {
            $response = $this->client->request($method, $uri, [
                'query' => $arguments,
            ]);
        } else {
            $response = $this->client->request($method, $uri, [
                'body' => json_encode($arguments),
            ]);
        }

        return json_decode($response->getBody(), true);
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function seedMembers()
    {
        $members = $this->sendCall('GET', '/guilds/{guild.id}/members', [
            'guild.id' => $this->guild_id,
            'limit'    => 1000,
        ]);

        foreach ($members as $member_attributes) {

            // skip all bot users
            if (array_key_exists('bot', $member_attributes['user']) && $member_attributes['user']['bot'])
                continue;

            $member = new DiscordMember($member_attributes);
            $this->members->put($member->getClientId(), $member);
        }
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Seat\Services\Exceptions\SettingException
     * @throws \Warlof\Seat\Connector\Exceptions\DriverSettingsException
     */
    private function seedRoles()
    {
        $roles = DiscordClient::getInstance()->sendCall('GET', '/guilds/{guild.id}/roles', [
            'guild.id' => $this->guild_id,
        ]);

        foreach ($roles as $role_attributes) {
            if ($role_attributes['name'] == '@everyone') continue;

            $role = new DiscordRole($role_attributes);
            $this->roles->put($role->getId(), $role);
        }
    }
}
