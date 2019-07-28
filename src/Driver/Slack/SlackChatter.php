<?php

namespace Warlof\Seat\Connector\Drivers\Slack;

use Warlof\Seat\Connector\Drivers\ISet;
use Warlof\Seat\Connector\Drivers\IUser;

/**
 * Class SlackChatter.
 *
 * @package Warlof\Seat\Connector\Drivers\Slack
 */
class SlackChatter implements IUser
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $email;

    /**
     * @var \Warlof\Seat\Connector\Drivers\ISet[]
     */
    private $channels;

    /**
     * SlackChatter constructor.
     *
     * @param string $id
     */
    public function __construct(array $attributes = [])
    {
        $this->channels = collect();
        $this->hydrate($attributes);
    }

    /**
     * @return string
     */
    public function getClientId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getUniqueId(): string
    {
        return $this->email;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return \Warlof\Seat\Connector\Drivers\ISet[]
     * @throws \Warlof\Seat\Connector\Drivers\Slack\Exceptions\SlackException
     */
    public function getGroups(): array
    {
        if ($this->channels->isEmpty()) {
            $channels = SlackClient::getInstance()->getGroups();

            $this->channels = collect(array_filter($channels, function ($channel) {
                return in_array($this, $channel->getMembers());
            }));
        }

        return $this->channels->toArray();
    }

    /**
     * @return \Warlof\Seat\Connector\Drivers\Slack\SlackChatter
     */
    public function hydrate(array $attributes = []): SlackChatter
    {
        $this->id   = $attributes['id'];
        $this->name = $attributes['name'];

        return $this;
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\ISet $group
     * @throws \Warlof\Seat\Connector\Drivers\Slack\Exceptions\SlackException
     */
    public function addGroup(ISet $group)
    {
        if (array_key_exists($group->getId(), $this->channels))
            return;

        SlackClient::getInstance()->sendCall('POST', '/conversations.invite', [
            'channel' => $group->getId(),
            'users'   => implode(',', [$this->id]),
        ]);

        $this->channels->put($group->getId(), $group);
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\ISet $group
     * @throws \Warlof\Seat\Connector\Drivers\Slack\Exceptions\SlackException
     */
    public function removeGroup(ISet $group)
    {
        if (! array_key_exists($group->getId(), $this->channels))
            return;

        SlackClient::getInstance()->sendCall('POST', '/conversations.kick', [
            'channel' => $group->getId(),
            'user'    => $this->id,
        ]);

        $this->channels->pull($group->getId());
    }
}
