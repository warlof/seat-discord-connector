<?php

namespace Warlof\Seat\Connector\Discord\Drivers\Teamspeak;

use Warlof\Seat\Connector\Discord\Drivers\IClient;
use Warlof\Seat\Connector\Discord\Drivers\IPermissionGroup;
use Warlof\Seat\Connector\Discord\Drivers\IUser;
use Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\CommandException;
use Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ConnexionException;
use Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\LoginException;
use Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ServerException;

/**
 * Class TeamspeakClient.
 *
 * @package Warlof\Seat\Connector\Discord\Drivers\Teamspeak
 */
class TeamspeakClient implements IClient
{
    /**
     * @var \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\TeamspeakClient
     */
    private static $instance;

    /**
     * @var \Warlof\Seat\Connector\Discord\Drivers\IUser[]
     */
    private $speakers;

    /**
     * @var \Warlof\Seat\Connector\Discord\Drivers\IPermissionGroup[]
     */
    private $server_groups;

    /**
     * @var \ts3admin
     */
    private $client;

    /**
     * @var string
     */
    private $server_host;

    /**
     * @var int
     */
    private $server_port;

    /**
     * @var int
     */
    private $query_port;

    /**
     * @var string
     */
    private $query_username;

    /**
     * @var string
     */
    private $query_password;

    /**
     * TeamspeakClient constructor.
     *
     * @param array $parameters
     */
    private function __construct(array $parameters)
    {
        $this->server_host    = $parameters['server_host'];
        $this->server_port    = $parameters['server_port'];
        $this->query_port     = $parameters['query_port'];
        $this->query_username = $parameters['query_username'];
        $this->query_password = $parameters['query_password'];

        $this->speakers      = collect();
        $this->server_groups = collect();
    }

    /**
     * @param array $arguments
     * @return \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\TeamspeakClient
     */
    public static function getInstance(array $arguments = []): IClient
    {
        if (! isset(self::$instance))
            self::$instance = new TeamspeakClient($arguments);

        return self::$instance;
    }

    /**
     * @return \Warlof\Seat\Connector\Discord\Drivers\IUser[]
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\CommandException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ConnexionException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\LoginException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ServerException
     */
    public function getUsers(): array
    {
        if ($this->speakers->isEmpty())
            $this->seedSpeakers();

        return $this->speakers->toArray();
    }

    /**
     * @return \Warlof\Seat\Connector\Discord\Drivers\IPermissionGroup[]
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ConnexionException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\LoginException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ServerException
     */
    public function getGroups(): array
    {
        if ($this->server_groups->isEmpty())
            $this->seedServerGroups();

        return $this->server_groups->toArray();
    }

    /**
     * @param string $id
     * @return \Warlof\Seat\Connector\Discord\Drivers\IUser|null
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\CommandException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ConnexionException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\LoginException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ServerException
     */
    public function getUser(string $id): ?IUser
    {
        if ($this->speakers->isEmpty())
            $this->seedSpeakers();

        return $this->speakers->get($id);
    }

    /**
     * @param string $id
     * @return \Warlof\Seat\Connector\Discord\Drivers\IPermissionGroup|null
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ConnexionException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\LoginException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ServerException
     */
    public function getGroup(string $id): ?IPermissionGroup
    {
        if ($this->server_groups->isEmpty())
            $this->seedServerGroups();

        return $this->server_groups->get($id);
    }

    /**
     * @param \Warlof\Seat\Connector\Discord\Drivers\IUser $user
     */
    public function addSpeaker(IUser $user)
    {
        $this->speakers->put($user->getClientId(), $user);
    }

    /**
     * @param \Warlof\Seat\Connector\Discord\Drivers\IPermissionGroup $group
     */
    public function addServerGroup(IPermissionGroup $group)
    {
        $this->server_groups->put($group->getId(), $group);
    }

    /**
     * @param string $command
     * @param array $arguments
     * @return mixed
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\CommandException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ConnexionException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\LoginException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ServerException
     */
    public function sendCall(string $command, array $arguments = [])
    {
        $this->connect();
        $response = call_user_func_array([$this->client, $command], $arguments);

        if (! $this->client->succeeded($response))
            throw new CommandException($response['errors']);

        return $response;
    }

    /**
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ConnexionException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\LoginException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ServerException
     */
    private function connect()
    {
        if ($this->isConnected())
            return;

        $this->client = new \ts3admin($this->server_host, $this->query_port);

        $response = $this->client->connect();
        if (! $this->client->succeeded($response))
            throw new ConnexionException($response['errors']);

        $response = $this->client->login($this->query_username, $this->query_password);
        if (! $this->client->succeeded($response))
            throw new LoginException($response['errors']);

        $response = $this->client->selectServer($this->server_port);
        if (! $this->client->succeeded($response))
            throw new ServerException($response['errors']);
    }

    /**
     * @return bool
     */
    private function isConnected(): bool
    {
        if (is_null($this->client))
            return false;

        return $this->client->succeeded($this->client->connect());
    }

    /**
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\CommandException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ConnexionException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\LoginException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ServerException
     */
    private function seedSpeakers()
    {
        // ensure we have an open socket to the server
        if (! $this->isConnected())
            $this->connect();

        $speakers = $this->client->clientDbList();
        if (! $this->client->succeeded($speakers))
            throw new CommandException($speakers['errors']);

        foreach ($speakers['data'] as $speaker_attributes) {
            $speaker = new TeamspeakSpeaker($speaker_attributes);
            $this->speakers->put($speaker->getClientId(), $speaker);
        }
    }

    /**
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ConnexionException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\LoginException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ServerException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\CommandException
     */
    private function seedServerGroups()
    {
        // ensure we have an open socket to the server
        if (! $this->isConnected())
            $this->connect();

        $server_info = $this->client->serverInfo();
        if (! $this->client->succeeded($server_info))
            throw new CommandException($server_info['errors']);

        $server_groups = $this->client->serverGroupList(1);
        if (! $this->client->succeeded($server_groups))
            throw new ConnexionException($server_groups['errors']);

        foreach ($server_groups['data'] as $group_attributes) {

            // ignore default server group
            if ($group_attributes['sgid'] == $server_info['data']['virtualserver_default_server_group'])
                continue;

            $server_group = new TeamspeakServerGroup($group_attributes);
            $this->server_groups->put($server_group->getId(), $server_group);
        }
    }
}
