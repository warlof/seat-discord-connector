<?php

namespace Warlof\Seat\Connector\Drivers\Discord\Driver;

use Warlof\Seat\Connector\Drivers\ISet;
use Warlof\Seat\Connector\Drivers\IUser;

/**
 * Class DiscordRole.
 *
 * @package Warlof\Seat\Connector\Drivers\Discord\Driver
 */
class DiscordRole implements ISet
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
     * DiscordRole constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes = [])
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
     * @return \Warlof\Seat\Connector\Drivers\IUser[]
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Seat\Services\Exceptions\SettingException
     * @throws \Warlof\Seat\Connector\Exceptions\DriverSettingsException
     */
    public function getMembers(): array
    {
        if ($this->members->isEmpty()) {
            $users = DiscordClient::getInstance()->getUsers();

            $this->members = collect(array_filter($users, function ($user) {
                return in_array($this, $user->getSets());
            }));
        }

        return $this->members->toArray();
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\IUser $user
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Seat\Services\Exceptions\SettingException
     * @throws \Warlof\Seat\Connector\Exceptions\DriverSettingsException
     */
    public function addMember(IUser $user)
    {
        if (in_array($user, $this->getMembers()))
            return;

        DiscordClient::getInstance()->sendCall('PUT', '/guilds/{guild.id}/members/{user.id}/roles/{role.id}', [
            'guild.id' => DiscordClient::getInstance()->getGuildId(),
            'role.id'  => $this->id,
            'user.id'  => $user->getClientId(),
        ]);

        $this->members->put($user->getClientId(), $user);
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\IUser $user
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Seat\Services\Exceptions\SettingException
     * @throws \Warlof\Seat\Connector\Exceptions\DriverSettingsException
     */
    public function removeMember(IUser $user)
    {
        if (! in_array($user, $this->getMembers()))
            return;

        DiscordClient::getInstance()->sendCall('DELETE', '/guilds/{guild.id}/members/{user.id}/roles/{role.id}', [
            'guild.id' => DiscordClient::getInstance()->getGuildId(),
            'role.id'  => $this->id,
            'user.id'  => $user->getClientId(),
        ]);

        $this->members->pull($user->getClientId());
    }

    /**
     * @param array $attributes
     * @return \Warlof\Seat\Connector\Drivers\Discord\Driver\DiscordRole
     */
    public function hydrate(array $attributes = []): DiscordRole
    {
        $this->id   = $attributes['id'];
        $this->name = $attributes['name'];

        return $this;
    }
}
