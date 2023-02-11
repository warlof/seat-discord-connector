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

namespace Warlof\Seat\Connector\Drivers\Discord\Tests;

use GuzzleHttp\Psr7\Response;
use Orchestra\Testbench\TestCase;
use Warlof\Seat\Connector\Drivers\Discord\Driver\DiscordClient;
use Warlof\Seat\Connector\Drivers\Discord\Driver\DiscordMember;
use Warlof\Seat\Connector\Drivers\Discord\Driver\DiscordRole;
use Warlof\Seat\Connector\Drivers\Discord\Tests\Fetchers\TestFetcher;
use Warlof\Seat\Connector\Exceptions\DriverException;

/**
 * Class DiscordMemberTest.
 *
 * @package Warlof\Seat\Connector\Drivers\Discord\Tests
 */
class DiscordMemberTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/migrations');

        $settings = (object) [
            'client_id' => 123456890,
            'client_secret' => 'abcde-4fs8s7f51sq654g',
            'bot_token' => 'sdf4df6g.es4dfg97f.ze978dfg49fh4fg4',
            'guild_id' => 41771983423143937,
            'owner_id' => 80351110224678912,
        ];

        setting(['seat-connector.drivers.discord', $settings], true);
    }

    protected function tearDown(): void
    {
        DiscordClient::tearDown();

        parent::tearDown();
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('discord-connector.config.fetcher', TestFetcher::class);
        $app['config']->set('discord-connector.config.version', 'dev');
    }

    public function testGetClientId()
    {
        $artifact = '80351110224678913';

        $user = new DiscordMember([
            'nick' => 'Member 2',
            'roles' => [],
            'user' => [
                'id' => $artifact,
                'username' => 'Mike',
            ],
        ]);

        $this->assertEquals($artifact, $user->getClientId());
    }

    public function testGetUniqueId()
    {
        $artifact = '80351110224678913';

        $user = new DiscordMember([
            'nick'  => 'Member 2',
            'roles' => [],
            'user'  => [
                'id'       => '80351110224678913',
                'username' => 'Mike',
                'email'    => $artifact
            ],
        ]);

        $this->assertEquals($artifact, $user->getUniqueId());
    }

    public function testGetName()
    {
        $artifact = 'Member 2';

        $user = new DiscordMember([
            'nick' => $artifact,
            'roles' => [],
            'user' => [
                'id' => '80351110224678913',
                'username' => 'Mike',
            ],
        ]);

        $this->assertEquals($artifact, $user->getName());
    }

    public function testGetSets()
    {
        config([
            'discord-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/roles.json')),
            ],
        ]);

        $artifact = new DiscordRole([
            'id' => '41771983423143939',
            'name' => 'ANOTHER ROLE',
        ]);

        $user = new DiscordMember([
            'nick' => 'Jeremy',
            'roles' => [
                '41771983423143939',
            ],
            'user' => [
                'id' => '9687651657897975421',
                'username' => 'Jeremy',
            ],
        ]);

        $this->assertEquals([$artifact->getId() => $artifact], $user->getSets());
    }

    public function testSetName()
    {
        config([
            'discord-connector.config.mocks' => [
                new Response(204),
            ],
        ]);

        $artifact = 'Mike';

        $user = new DiscordMember([
            'nick'  => 'Member 2',
            'roles' => [],
            'user'  => [
                'id'       => '80351110224678913',
                'username' => $artifact,
                'email'    => 'test@example.com',
            ],
        ]);

        $user->setName('Georges');

        $this->assertNotEquals($artifact, $user->getName());
    }

    public function testSetNameGuzzleException()
    {
        config([
            'discord-connector.config.mocks' => [
                new Response(400),
            ],
        ]);

        $user = new DiscordMember([
            'nick'  => 'Member 2',
            'roles' => [],
            'user'  => [
                'id'       => '80351110224678913',
                'username' => 'Mike',
                'email'    => 'test@example.com',
            ],
        ]);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Unable to change user name from Member 2 to Georges.');

        $user->setName('Georges');
    }

    public function testAddSet()
    {
        config([
            'discord-connector.config.mocks' => [
                new Response(204),
            ],
        ]);

        $role = new DiscordRole([
            'id'   => '41771983423143934',
            'name' => 'ANOTHER ROLE',
        ]);

        $user = new DiscordMember([
            'nick'  => 'Member 5',
            'roles' => [],
            'user'  => [
                'id'       => '80351110224878913',
                'username' => 'Bob',
                'email'    => 'test@example.com',
            ],
        ]);

        $user->addSet($role);
        $this->assertEquals([$role->getId() => $role], $user->getSets());

        $user->addSet($role);
        $this->assertEquals([$role->getId() => $role], $user->getSets());
    }

    public function testAddSetGuzzleException()
    {
        config([
            'discord-connector.config.mocks' => [
                new Response(400),
            ],
        ]);

        $role = new DiscordRole([
            'id'   => '41771983423143934',
            'name' => 'ANOTHER ROLE',
        ]);

        $user = new DiscordMember([
            'nick'  => 'Member 5',
            'roles' => [],
            'user'  => [
                'id'       => '80351110224878913',
                'username' => 'Bob',
                'email'    => 'test@example.com',
            ],
        ]);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Unable to add set ANOTHER ROLE to the user Member 5.');

        $user->addSet($role);
    }

    public function testRemoveSet()
    {
        config([
            'discord-connector.config.mocks' => [
                new Response(204),
                new Response(204),
            ],
        ]);

        $role = new DiscordRole([
            'id'   => '41771983423143934',
            'name' => 'ANOTHER ROLE',
        ]);

        $user = new DiscordMember([
            'nick'  => 'Member 5',
            'roles' => [],
            'user'  => [
                'id'       => '80351110224878913',
                'username' => 'Bob',
                'email'    => 'test@example.com',
            ],
        ]);

        $user->addSet($role);
        $this->assertEquals([$role->getId() => $role], $user->getSets());

        $user->removeSet($role);
        $this->assertEmpty($user->getSets());

        $user->removeSet($role);
        $this->assertEmpty($user->getSets());
    }

    public function testRemoveSetGuzzleException()
    {
        config([
            'discord-connector.config.mocks' => [
                new Response(204),
                new Response(400),
            ],
        ]);

        $role = new DiscordRole([
            'id'   => '41771983423143934',
            'name' => 'ANOTHER ROLE',
        ]);

        $user = new DiscordMember([
            'nick'  => 'Member 5',
            'roles' => [],
            'user'  => [
                'id'       => '80351110224878913',
                'username' => 'Bob',
                'email'    => 'test@example.com',
            ],
        ]);

        $user->addSet($role);
        $this->assertEquals([$role->getId() => $role], $user->getSets());

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Unable to remove set ANOTHER ROLE from the user Member 5.');

        $user->removeSet($role);
    }
}
