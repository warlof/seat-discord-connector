<?php

namespace Warlof\Seat\Connector\Drivers\Slack;

use GuzzleHttp\Client;
use Warlof\Seat\Connector\Drivers\IClient;
use Warlof\Seat\Connector\Drivers\ISet;
use Warlof\Seat\Connector\Drivers\IUser;
use Warlof\Seat\Connector\Drivers\Slack\Exceptions\SlackException;

/**
 * Class SlackClient.
 *
 * @package Warlof\Seat\Connector\Drivers\Slack
 */
class SlackClient implements IClient
{
    const BASE_URI = 'https://slack.com/api/';

    /**
     * @var \Warlof\Seat\Connector\Drivers\Slack\SlackClient
     */
    private static $instance;

    /**
     * @var \GuzzleHttp\Client
     */
    private $client;

    /**
     * @var string
     */
    private $token;

    /**
     * @var \Warlof\Seat\Connector\Drivers\IUser[]
     */
    private $chatters;

    /**
     * @var \Warlof\Seat\Connector\Drivers\ISet[]
     */
    private $channels;

    /**
     * SlackClient constructor.
     *
     * @param array $parameters
     */
    private function __construct(array $parameters)
    {
        $this->token = $parameters['token'];

        $this->chatters  = collect();
        $this->channels = collect();
    }

    /**
     * @param array $parameters
     * @return \Warlof\Seat\Connector\Drivers\Slack\SlackClient
     */
    public static function getInstance(array $parameters = []): IClient
    {
        if (! isset(self::$instance))
            self::$instance = new SlackClient($parameters);

        return self::$instance;
    }

    /**
     * @return array
     * @throws \Warlof\Seat\Connector\Drivers\Slack\Exceptions\SlackException
     */
    public function getUsers(): array
    {
        if ($this->chatters->isEmpty())
            $this->seedChatters();

        return $this->chatters->toArray();
    }

    /**
     * @param string $id
     * @return \Warlof\Seat\Connector\Drivers\IUser|null
     * @throws \Warlof\Seat\Connector\Drivers\Slack\Exceptions\SlackException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getUser(string $id): ?IUser
    {
        if ($this->chatters->isEmpty())
            $this->seedChatters();

        $chatter = $this->chatters->get($id);

        if (is_null($chatter)) {
            $body = SlackClient::getInstance()->sendCall('GET', '/users.info', [
                'user' => $id,
            ]);

            $chatter = new SlackChatter((array) $body->user);
            $this->chatters->put($chatter->getClientId(), $chatter);
        }

        return $chatter;
    }

    /**
     * @return array
     * @throws \Warlof\Seat\Connector\Drivers\Slack\Exceptions\SlackException
     */
    public function getSets(): array
    {
        if ($this->channels->isEmpty())
            $this->seedChannels();

        return $this->channels->toArray();
    }

    /**
     * @param string $id
     * @return \Warlof\Seat\Connector\Drivers\ISet|null
     * @throws \Warlof\Seat\Connector\Drivers\Slack\Exceptions\SlackException
     */
    public function getSet(string $id): ?ISet
    {
        $group = $this->channels->get($id);

        if (is_null($group)) {
            $body = SlackClient::getInstance()->sendCall('GET', '/conversations.info', [
                'channel' => $id,
            ]);

            $group = new SlackChannel((array) $body->channel);
        }

        return $group;
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array $arguments
     * @return object
     * @throws \Warlof\Seat\Connector\Drivers\Slack\Exceptions\SlackException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function sendCall(string $method, string $endpoint, array $arguments = [])
    {
        $uri = ltrim($endpoint, '/');

        if (is_null($this->client))
            $this->client = new Client([
                'base_uri' => self::BASE_URI,
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $this->token),
                ],
            ]);

        $response = $this->client->request($method, $uri, [
            'query' => $arguments,
        ]);

        $body = json_decode($response->getBody());

        if (! $body->ok)
            throw new SlackException($body->error);

        return $body;
    }

    /**
     * @throws \Warlof\Seat\Connector\Drivers\Slack\Exceptions\SlackException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function seedChannels()
    {
        $body = SlackClient::getInstance()->sendCall('GET', '/conversations.list', [
            'exclude_archived' => true,
            'types'            => implode(',', ['public_channel', 'private_channel']),
        ]);

        foreach ($body->channels as $attributes) {
            $channel = new SlackChannel((array) $attributes);
            $this->channels->put($channel->getId(), $channel);
        }
    }

    /**
     * @throws \Warlof\Seat\Connector\Drivers\Slack\Exceptions\SlackException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function seedChatters()
    {
        $body = SlackClient::getInstance()->sendCall('GET', '/users.list');

        foreach ($body->members as $attributes) {
            $chatter = new SlackChatter((array) $attributes);
            $this->chatters->put($chatter->getClientId(), $chatter);
        }
    }
}
