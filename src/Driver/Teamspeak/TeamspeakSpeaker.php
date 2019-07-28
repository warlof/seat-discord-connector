<?php

namespace Warlof\Seat\Connector\Discord\Drivers\Teamspeak;

use Warlof\Seat\Connector\Discord\Drivers\IPermissionGroup;
use Warlof\Seat\Connector\Discord\Drivers\IUser;

/**
 * Class TeamspeakSpeaker.
 *
 * @package Warlof\Seat\Connector\Discord\Drivers\Teamspeak
 */
class TeamspeakSpeaker implements IUser
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $unique_id;

    /**
     * @var string
     */
    private $nickname;

    /**
     * @var \Warlof\Seat\Connector\Discord\Drivers\IPermissionGroup[]
     */
    private $server_groups;

    /**
     * TeamspeakSpeaker constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->server_groups = collect();
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
        return $this->unique_id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->nickname;
    }

    /**
     * @return \Warlof\Seat\Connector\Discord\Drivers\IPermissionGroup[]
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\CommandException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ConnexionException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\LoginException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ServerException
     */
    public function getGroups(): array
    {
        if ($this->server_groups->isEmpty()) {
            $response = TeamspeakClient::getInstance()->sendCall('serverGroupsByClientID', [
                'cldbid' => $this->id,
            ]);

            foreach ($response['data'] as $group_attributes) {

                $group = TeamspeakClient::getInstance()->getGroup($group_attributes['sgid']);

                if (is_null($group)) {
                    $group = new TeamspeakServerGroup($group_attributes);
                    TeamspeakClient::getInstance()->addServerGroup($group);
                }

                $this->server_groups->put($group->getId(), $group);
            }
        }

        return $this->server_groups->toArray();
    }

    /**
     * @param \Warlof\Seat\Connector\Discord\Drivers\IPermissionGroup $group
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\CommandException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ConnexionException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\LoginException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ServerException
     */
    public function addGroup(IPermissionGroup $group)
    {
        if (in_array($group, $this->getGroups()))
            return;

        TeamspeakClient::getInstance()->sendCall('serverGroupAddClient', [
            'sgid'   => $group->getId(),
            'cldbid' => $this->id,
        ]);

        $this->server_groups->put($group->getId(), $group);
    }

    /**
     * @param \Warlof\Seat\Connector\Discord\Drivers\IPermissionGroup $group
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\CommandException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ConnexionException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\LoginException
     * @throws \Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions\ServerException
     */
    public function removeGroup(IPermissionGroup $group)
    {
        if (! in_array($group, $this->getGroups()))
            return;

        TeamspeakClient::getInstance()->sendCall('serverGroupDeleteClient', [
            'sgid'   => $group->getId(),
            'cldbid' => $this->id,
        ]);

        $this->server_groups->pull($group->getId());
    }

    /**
     * @param array $attributes
     * @return $this
     */
    public function hydrate(array $attributes)
    {
        $this->id        = $attributes['cldbid'];
        $this->unique_id = $attributes['client_unique_identifier'];
        $this->nickname  = $attributes['client_nickname'];

        return $this;
    }
}
