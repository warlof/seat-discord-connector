<?php

namespace Warlof\Seat\Connector\Discord\Drivers\Teamspeak;

use Warlof\Seat\Connector\Discord\Drivers\IPermissionGroup;
use Warlof\Seat\Connector\Discord\Drivers\IUser;

/**
 * Class TeamspeakServerGroup.
 *
 * @package Warlof\Seat\Connector\Discord\Drivers\Teamspeak
 */
class TeamspeakServerGroup implements IPermissionGroup
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
     * @var \Warlof\Seat\Connector\Discord\Drivers\IUser[]
     */
    private $members;

    /**
     * TeamspeakServerGroup constructor.
     *
     * @param array $attributes
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
     * @return \Warlof\Seat\Connector\Discord\Drivers\IUser[]
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\CommandException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ConnexionException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\LoginException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ServerException
     */
    public function getMembers(): array
    {
        if ($this->members->isEmpty()) {
            $response = TeamspeakClient::getInstance()->sendCall('serverGroupClientList', [
                'sgid'  => $this->id,
                'names' => true,
            ]);

            foreach ($response['data'] as $user_attributes) {
                if (! array_key_exists('cldbid', $user_attributes))
                    continue;

                $member = TeamspeakClient::getInstance()->getUser($user_attributes['cldbid']);

                if (is_null($member)) {
                    $member = new TeamspeakSpeaker($user_attributes);
                    TeamspeakClient::getInstance()->addSpeaker($member);
                }

                $this->members->put($member->getClientId(), $member);
            }
        }

        return $this->members->toArray();
    }

    /**
     * @param \Warlof\Seat\Connector\Discord\Drivers\IUser $user
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\CommandException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ConnexionException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\LoginException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ServerException
     */
    public function addMember(IUser $user)
    {
        if (in_array($user, $this->getMembers()))
            return;

        TeamspeakClient::getInstance()->sendCall('serverGroupAddClient', [
            'sgid'   => $this->id,
            'cldbid' => $user->getClientId(),
        ]);

        $this->members->put($user->getClientId(), $user);
    }

    /**
     * @param \Warlof\Seat\Connector\Discord\Drivers\IUser $user
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\CommandException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ConnexionException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\LoginException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ServerException
     */
    public function removeMember(IUser $user)
    {
        if (! in_array($user, $this->getMembers()))
            return;

        TeamspeakClient::getInstance()->sendCall('serverGroupDeleteClient', [
            'sgid'   => $this->id,
            'cldbid' => $user->getClientId(),
        ]);

        $this->members->pull($user->getClientId());
    }

    /**
     * @param array $attributes
     * @return $this
     */
    public function hydrate(array $attributes)
    {
        $this->id   = $attributes['sgid'];
        $this->name = $attributes['name'];

        return $this;
    }
}
