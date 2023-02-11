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
 * Class DiscordRoleTest.
 *
 * @package Warlof\Seat\Connector\Drivers\Discord\Tests
 */
class DiscordRoleTest extends TestCase
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

    public function testGetId()
    {
        $artifact = '41771983423143937';

        $set = new DiscordRole([
            'id'   => $artifact,
            'name' => 'TEST ROLE',
        ]);

        $this->assertEquals($artifact, $set->getId());
    }

    public function testGetName()
    {
        $artifact = 'TEST ROLE';

        $set = new DiscordRole([
            'id'   => '41771983423143937',
            'name' => $artifact,
        ]);

        $this->assertEquals($artifact, $set->getName());
    }

    public function testAddMember()
    {
        config([
            'discord-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/members.json')),
                new Response(200, [], '[]'),
                new Response(204),
            ],
        ]);

        $role = new DiscordRole([
            'id'   => '41771983423143937',
            'name' => 'TEST ROLE',
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

        $role->addMember($user);
        $this->assertEquals([$user->getClientId() => $user], $role->getMembers());

        $role->addMember($user);
        $this->assertEquals([$user->getClientId() => $user], $role->getMembers());
    }

    public function testAddMemberGuzzleException()
    {
        config([
            'discord-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/members.json')),
                new Response(200, [], '[]'),
                new Response(400),
            ],
        ]);

        $role = new DiscordRole([
            'id'   => '41771983423143937',
            'name' => 'TEST ROLE',
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
        $this->expectExceptionMessage('Unable to add user Member 2 as a member of set TEST ROLE.');

        $role->addMember($user);
    }

    public function testRemoveMember()
    {
        config([
            'discord-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/members.json')),
                new Response(200, [], '[]'),
                new Response(204),
                new Response(204),
            ],
        ]);

        $role = new DiscordRole([
            'id'   => '41771983423143937',
            'name' => 'TEST ROLE',
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

        $role->addMember($user);
        $this->assertEquals([$user->getClientId() => $user], $role->getMembers());

        $role->removeMember($user);
        $this->assertEmpty($role->getMembers());

        $role->removeMember($user);
        $this->assertEmpty($role->getMembers());
    }

    public function testRemoveMemberGuzzleException()
    {
        config([
            'discord-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/members.json')),
                new Response(200, [], '[]'),
                new Response(204),
                new Response(400),
            ],
        ]);

        $role = new DiscordRole([
            'id'   => '41771983423143937',
            'name' => 'TEST ROLE',
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

        $role->addMember($user);
        $this->assertEquals([$user->getClientId() => $user], $role->getMembers());

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Unable to remove user Member 2 from set TEST ROLE.');

        $role->removeMember($user);
    }
}
