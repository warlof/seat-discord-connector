<?php

namespace Warlof\Seat\Connector\Drivers\Slack;

use Warlof\Seat\Connector\Drivers\ISet;
use Warlof\Seat\Connector\Drivers\IUser;

/**
 * Class SlackChannel.
 *
 * @package Warlof\Seat\Connector\Drivers\Slack
 */
class SlackChannel implements ISet
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
     * @var \Warlof\Seat\Connector\Drivers\IUser[]
     */
    private $members;

    /**
     * @var bool
     */
    private $private;

    /**
     * SlackChannel constructor.
     *
     * @param string $id
     */
    public function __construct(array $attributes = [])
    {
        $this->members = collect();
        $this->hydrate($attributes);
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Slack\Exceptions\SlackException
     */
    public function getMembers(): array
    {
        if ($this->members->isEmpty())
            $this->seedMembers();

        return $this->members->toArray();
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\IUser $user
     * @throws \Warlof\Seat\Connector\Drivers\Slack\Exceptions\SlackException
     */
    public function addMember(IUser $user)
    {
        if (in_array($user, $this->getMembers()))
            return;

        SlackClient::getInstance()->sendCall('POST', '/conversations.invite', [
            'channel' => $this->id,
            'users'   => implode(',', [$user->getClientId()]),
        ]);

        $this->members->put($user->getClientId(), $user);
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\IUser $user
     * @throws \Warlof\Seat\Connector\Drivers\Slack\Exceptions\SlackException
     */
    public function removeMember(IUser $user)
    {
        if (! in_array($user, $this->getMembers()))
            return;

        SlackClient::getInstance()->sendCall('POST', '/conversations.kick', [
            'channel' => $this->id,
            'user'    => $user->getClientId(),
        ]);

        $this->members->pull($user->getClientId());
    }

    /**
     * @return \Warlof\Seat\Connector\Drivers\Slack\SlackChannel
     */
    public function hydrate(array $attributes = []): SlackChannel
    {
        $this->id      = $attributes['id'];
        $this->name    = $attributes['name'];
        $this->private = $attributes['is_group'];

        return $this;
    }

    /**
     * @throws \Warlof\Seat\Connector\Drivers\Slack\Exceptions\SlackException
     */
    private function seedMembers()
    {
        $body = SlackClient::getInstance()->sendCall('GET', '/conversations.members', [
            'channel' => $this->id,
        ]);

        foreach ($body->members as $member_id) {
            $entity = SlackClient::getInstance()->getUser($member_id);

            $this->members->put($entity->getClientId(), $entity);
        }
    }
}
