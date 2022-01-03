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

use GuzzleHttp\Exception\GuzzleException;
use Warlof\Seat\Connector\Drivers\ISet;
use Warlof\Seat\Connector\Drivers\IUser;
use Warlof\Seat\Connector\Exceptions\DriverException;

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
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function getMembers(): array
    {
        if ($this->members->isEmpty()) {
            $users = DiscordClient::getInstance()->getUsers();

            $this->members = collect(array_filter($users, function (IUser $user) {
                return in_array($this, $user->getSets());
            }));
        }

        return $this->members->toArray();
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\IUser $user
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function addMember(IUser $user)
    {
        if (in_array($user, $this->getMembers()))
            return;

        if (! DiscordClient::getInstance()->checkCanAddRoles($this->name, $this->id))
            return;

        try {
            DiscordClient::getInstance()->sendCall('PUT', '/guilds/{guild.id}/members/{user.id}/roles/{role.id}', [
                'guild.id' => DiscordClient::getInstance()->getGuildId(),
                'role.id' => $this->id,
                'user.id' => $user->getClientId(),
            ]);
        } catch (GuzzleException $e) {
            throw new DriverException(
                sprintf('Unable to add user %s as a member of set %s.', $user->getName(), $this->getName()),
                0,
                $e);
        }

        $this->members->put($user->getClientId(), $user);
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\IUser $user
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function removeMember(IUser $user)
    {
        if (! in_array($user, $this->getMembers()))
            return;

        if (! DiscordClient::getInstance()->checkCanRemoveRoles($this->name, $this->id))
            return;

        try {
            DiscordClient::getInstance()->sendCall('DELETE', '/guilds/{guild.id}/members/{user.id}/roles/{role.id}', [
                'guild.id' => DiscordClient::getInstance()->getGuildId(),
                'role.id' => $this->id,
                'user.id' => $user->getClientId(),
            ]);
        } catch (GuzzleException $e) {
            logger()->error(sprintf('[seat-connector][discord] %s', $e->getMessage()));
            throw new DriverException(
                sprintf('Unable to remove user %s from set %s.', $user->getName(), $this->getName()),
                0,
                $e);
        }

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
