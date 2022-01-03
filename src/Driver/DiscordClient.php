<?php

/**
 * This file is part of SeAT Discord Connector.
 *
 * Copyright (C) 2019, 2020  Warlof Tutsimo <loic.leuilliot@gmail.com>
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

namespace Warlof\Seat\Connector\Drivers\Discord\Driver;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use Seat\Services\Exceptions\SettingException;
use Warlof\Seat\Connector\Drivers\IClient;
use Warlof\Seat\Connector\Drivers\ISet;
use Warlof\Seat\Connector\Drivers\IUser;
use Warlof\Seat\Connector\Exceptions\DriverException;
use Warlof\Seat\Connector\Exceptions\DriverSettingsException;
use Warlof\Seat\Connector\Exceptions\InvalidDriverIdentityException;

/**
 * Class DiscordClient.
 *
 * @package Warlof\Seat\Connector\Drivers\Discord\Driver
 */
class DiscordClient implements IClient
{
    CONST BASE_URI = 'https://discord.com/api';

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
     * @var string
     */
    private $owner_id;

    /**
     * @var  Warlof\Seat\Connector\Drivers\Discord\Driver\DiscordRoleFilter
     */
    private $visible_roles;

    /**
     * @var  Warlof\Seat\Connector\Drivers\Discord\Driver\DiscordRoleFilter
     */
    private $can_add_roles;

    /**
     * @var  Warlof\Seat\Connector\Drivers\Discord\Driver\DiscordRoleFilter
     */
    private $can_remove_roles;

    /**
     * DiscordClient constructor.
     *
     * @param array $parameters
     */
    private function __construct(array $parameters)
    {
        $this->guild_id  = $parameters['guild_id'];
        $this->bot_token = $parameters['bot_token'];
        $this->owner_id  = $parameters['owner_id'];
        $this->visible_roles = new DiscordRoleFilter($parameters['visible_roles']);
        $this->can_add_roles = new DiscordRoleFilter($parameters['can_add_roles']);
        $this->can_remove_roles = new DiscordRoleFilter($parameters['can_remove_roles']);

        $this->members = collect();
        $this->roles   = collect();

        $fetcher = config('discord-connector.config.fetcher');
        $base_uri = sprintf('%s/%s', rtrim(self::BASE_URI, '/'), self::VERSION);
        $this->client = new $fetcher($base_uri, $this->bot_token);
    }

    /**
     * @return \Warlof\Seat\Connector\Drivers\Discord\Driver\DiscordClient
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public static function getInstance(): IClient
    {
        if (! isset(self::$instance)) {
            try {
                $settings = setting('seat-connector.drivers.discord', true);
            } catch (SettingException $e) {
                throw new DriverException($e->getMessage(), $e->getCode(), $e);
            }

            if (is_null($settings) || ! is_object($settings))
                throw new DriverSettingsException('The Driver has not been configured yet.');

            if (! property_exists($settings, 'guild_id') || is_null($settings->guild_id) || $settings->guild_id == '')
                throw new DriverSettingsException('Parameter guild_id is missing.');

            if (! property_exists($settings, 'bot_token') || is_null($settings->bot_token) || $settings->bot_token == '')
                throw new DriverSettingsException('Parameter bot_token is missing.');

            self::$instance = new DiscordClient([
                'guild_id'         => $settings->guild_id,
                'bot_token'        => $settings->bot_token,
                'owner_id'         => property_exists($settings, 'owner_id') ? $settings->owner_id : null,
                'visible_roles'    => property_exists($settings, 'visible_roles') ? $settings->visible_roles : DiscordRoleFilter::DEFAULT_VISIBLE_ROLES,
                'can_add_roles'    => property_exists($settings, 'can_add_roles') ? $settings->can_add_roles : DiscordRoleFilter::DEFAULT_CAN_ADD_ROLES,
                'can_remove_roles' => property_exists($settings, 'can_remove_roles') ? $settings->can_remove_roles : DiscordRoleFilter::DEFAULT_CAN_REMOVE_ROLES,
            ]);
        }

        return self::$instance;
    }

    /**
     * Reset the instance
     */
    public static function tearDown()
    {
        self::$instance = null;
    }

    /**
     * @return \Warlof\Seat\Connector\Drivers\IUser[]
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function getUsers(): array
    {
        if ($this->members->isEmpty()) {
            try {
                $this->seedMembers();
            } catch (GuzzleException $e) {
                throw new DriverException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->members->toArray();
    }

    /**
     * @param string $id
     * @return \Warlof\Seat\Connector\Drivers\IUser|null
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function getUser(string $id): ?IUser
    {
        $member = $this->members->get($id);

        if (! is_null($member))
            return $member;

        try {
            $member = $this->sendCall('GET', '/guilds/{guild.id}/members/{user.id}', [
                'guild.id' => $this->guild_id,
                'user.id' => $id,
            ]);
        } catch (ClientException $e) {
            logger()->error($e->getMessage(), $e->getTrace());

            $error = json_decode($e->getResponse()->getBody());

            if (! is_null($error) && property_exists($error, 'code')) {
                switch ($error->code) {
                    // provided Guild is not found
                    // ref: https://discordapp.com/developers/docs/topics/opcodes-and-status-codes
                    case 10004:
                        throw new DriverSettingsException(sprintf('Configured Guild ID %s is invalid.', $this->guild_id));
                    // provided User is not found into the Guild
                    // ref: https://discordapp.com/developers/docs/topics/opcodes-and-status-codes
                    case 10007:
                        throw new InvalidDriverIdentityException(sprintf('User ID %s is not found in Guild %s.', $id, $this->guild_id));
                }
            }

            throw new DriverException($e->getMessage(), $e->getCode(), $e);
        } catch (GuzzleException $e) {
            throw new DriverException($e->getMessage(), $e->getCode(), $e);
        }

        $member = new DiscordMember((array) $member);
        $this->members->put($member->getClientId(), $member);

        return $member;
    }

    /**
     * @return \Warlof\Seat\Connector\Drivers\ISet[]
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function getSets(): array
    {
        if ($this->roles->isEmpty()) {
            try {
                $this->seedRoles();
            } catch (GuzzleException $e) {
                throw new DriverException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->roles->toArray();
    }

    /**
     * @param string $id
     * @return \Warlof\Seat\Connector\Drivers\ISet|null
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function getSet(string $id): ?ISet
    {
        if ($this->roles->isEmpty()) {
            try {
                $this->seedRoles();
            } catch (GuzzleException $e) {
                throw new DriverException($e->getMessage(), $e->getCode(), $e);
            }
        }

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
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Seat\Services\Exceptions\SettingException
     */
    public function getOwnerId(): string
    {
        if (is_null($this->owner_id)) {
            $guild = $this->sendCall('GET', '/guilds/{guild.id}', [
                'guild.id' => $this->guild_id,
            ]);

            $this->owner_id = $guild['owner_id'];

            $settings  = setting('seat-connector.drivers.discord', true);
            $settings->owner_id = $this->owner_id;

            setting(['seat-connector.drivers.discord', $settings], true);
        }

        return $this->owner_id;
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
            Arr::pull($arguments, $uri_parameter);
        }

        if ($method == 'GET') {
            $response = $this->client->request($method, $uri, [
                'query' => $arguments,
            ]);
        } else {
            $response = $this->client->request($method, $uri, [
                'body' => json_encode($arguments),
            ]);
        }

        logger()->debug(
            sprintf('[seat-connector][discord] [http %d, %s] %s -> /%s',
                $response->getStatusCode(), $response->getReasonPhrase(), $method, $uri)
        );

        return json_decode($response->getBody(), true);
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function seedMembers()
    {
        $after = null;

        do {
            $options= [
                'guild.id' => $this->guild_id,
                'limit'    => 1000,
            ];

            if ($after)
                $options['after'] = $after;

            $members = $this->sendCall('GET', '/guilds/{guild.id}/members', $options);

            if (empty($members))
                break;

            $after = end($members)['user']['id'];

            foreach ($members as $member_attributes) {

                // skip all bot users
                if (array_key_exists('bot', $member_attributes['user']) && $member_attributes['user']['bot'])
                    continue;

                $member = new DiscordMember($member_attributes);
                $this->members->put($member->getClientId(), $member);
            }
        } while (true);
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    private function seedRoles()
    {
        $roles = DiscordClient::getInstance()->sendCall('GET', '/guilds/{guild.id}/roles', [
            'guild.id' => $this->guild_id,
        ]);

        foreach ($roles as $role_attributes) {
            if ($role_attributes['name'] == '@everyone') continue;

            // ignore managed roles (ie: booster)
            if ($role_attributes['managed']) continue;

            if (! $this->checkVisibleRoles($role_attributes['name'], $role_attributes['id'])) continue;

            $role = new DiscordRole($role_attributes);
            $this->roles->put($role->getId(), $role);
        }
    }

    /**
     * @params any $args
     * @return bool
     */
    public function checkVisibleRoles(...$args) : bool
    {
        return $this->visible_roles->check(...$args);
    }

    /**
     * @params any $args
     * @return bool
     */
    public function checkCanAddRoles(...$args) : bool
    {
        return $this->can_add_roles->check(...$args);
    }

    /**
     * @params any $args
     * @return bool
     */
    public function checkCanRemoveRoles(...$args) : bool
    {
        return $this->can_remove_roles->check(...$args);
    }

}
