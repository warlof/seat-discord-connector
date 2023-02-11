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

/**
 * Class DiscordClientTest.
 *
 * @package Warlof\Seat\Connector\Drivers\Discord\Tests
 */
class DiscordClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/migrations');
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

    protected function tearDown(): void
    {
        DiscordClient::tearDown();

        parent::tearDown();
    }

    public function testGetOwnerIdFromApi()
    {
        $settings = (object) [
            'client_id' => 123456890,
            'client_secret' => 'abcde-4fs8s7f51sq654g',
            'bot_token' => 'sdf4df6g.es4dfg97f.ze978dfg49fh4fg4',
            'guild_id' => 41771983423143937,
        ];

        setting(['seat-connector.drivers.discord', $settings], true);

        config([
            'discord-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/guild.json')),
            ],
        ]);

        $artifact = '80351110224678912';

        $driver = DiscordClient::getInstance();

        $this->assertEquals($artifact, $driver->getOwnerId());
    }

    public function testGetOwnerIdFromSettings()
    {
        $settings = (object) [
            'client_id' => 123456890,
            'client_secret' => 'abcde-4fs8s7f51sq654g',
            'bot_token' => 'sdf4df6g.es4dfg97f.ze978dfg49fh4fg4',
            'guild_id' => 41771983423143937,
            'owner_id' => 80351110224678912,
        ];

        setting(['seat-connector.drivers.discord', $settings], true);

        $artifact = '80351110224678912';

        $driver = DiscordClient::getInstance();

        $this->assertEquals($artifact, $driver->getOwnerId());
    }

    public function testGetGuildId()
    {
        $settings = (object) [
            'client_id' => 123456890,
            'client_secret' => 'abcde-4fs8s7f51sq654g',
            'bot_token' => 'sdf4df6g.es4dfg97f.ze978dfg49fh4fg4',
            'guild_id' => 41771983423143937,
            'owner_id' => 80351110224678912,
        ];

        setting(['seat-connector.drivers.discord', $settings], true);

        $artifact = '41771983423143937';

        $driver = DiscordClient::getInstance();

        $this->assertEquals($artifact, $driver->getGuildId());
    }

    public function testGetAllUsers()
    {
        $settings = (object) [
            'client_id' => 123456890,
            'client_secret' => 'abcde-4fs8s7f51sq654g',
            'bot_token' => 'sdf4df6g.es4dfg97f.ze978dfg49fh4fg4',
            'guild_id' => 41771983423143937,
            'owner_id' => 80351110224678912,
        ];

        setting(['seat-connector.drivers.discord', $settings], true);

        config([
            'discord-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/members.json')),
                new Response(200, [], '[]'),
            ],
        ]);

        $driver = DiscordClient::getInstance();

        $artifact = [
            '80351110224678912' => new DiscordMember([
                'nick' => 'Member 1',
                'roles' => [],
                'user' => [
                    'id' => '80351110224678912',
                    'username' => 'Nelly',
                ],
            ]),
            '80351110224678913' => new DiscordMember([
                'nick' => 'Member 2',
                'roles' => [],
                'user' => [
                    'id' => '80351110224678913',
                    'username' => 'Mike',
                ],
            ]),
            '80351110224678914' => new DiscordMember([
                'nick' => 'Member 3',
                'roles' => [],
                'user' => [
                    'id' => '80351110224678914',
                    'username' => 'Clarke',
                ],
            ]),
        ];

        $this->assertEquals($artifact, $driver->getUsers());
    }

    public function testGetExistingSingleUser()
    {
        $settings = (object) [
            'client_id' => 123456890,
            'client_secret' => 'abcde-4fs8s7f51sq654g',
            'bot_token' => 'sdf4df6g.es4dfg97f.ze978dfg49fh4fg4',
            'guild_id' => 41771983423143937,
            'owner_id' => 80351110224678912,
        ];

        setting(['seat-connector.drivers.discord', $settings], true);

        config([
            'discord-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/members.json')),
                new Response(200, [], '[]'),
            ],
        ]);

        $user_id = '80351110224678913';
        $artifact = new DiscordMember([
            'nick' => 'Member 2',
            'roles' => [],
            'user' => [
                'id' => $user_id,
                'username' => 'Mike',
            ],
        ]);

        $driver = DiscordClient::getInstance();
        $driver->getUsers();

        $this->assertEquals($artifact, $driver->getUser($user_id));
    }

    public function testGetMissingSingleUser()
    {
        $settings = (object) [
            'client_id' => 123456890,
            'client_secret' => 'abcde-4fs8s7f51sq654g',
            'bot_token' => 'sdf4df6g.es4dfg97f.ze978dfg49fh4fg4',
            'guild_id' => 41771983423143937,
            'owner_id' => 80351110224678912,
        ];

        setting(['seat-connector.drivers.discord', $settings], true);

        config([
            'discord-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/member.json')),
            ],
        ]);

        $user_id = '80351110224678919';
        $artifact = new DiscordMember([
            'nick' => 'Member 4',
            'roles' => [],
            'user' => [
                'id' => $user_id,
                'username' => 'Jocelyn',
            ],
        ]);

        $driver = DiscordClient::getInstance();

        $this->assertEquals($artifact, $driver->getUser($user_id));
    }

    public function testGetAllSets()
    {
        $settings = (object) [
            'client_id' => 123456890,
            'client_secret' => 'abcde-4fs8s7f51sq654g',
            'bot_token' => 'sdf4df6g.es4dfg97f.ze978dfg49fh4fg4',
            'guild_id' => 41771983423143937,
            'owner_id' => 80351110224678912,
        ];

        setting(['seat-connector.drivers.discord', $settings], true);

        config([
            'discord-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/roles.json')),
            ],
        ]);

        $artifact = [
            41771983423143936 => new DiscordRole([
                'id'   => '41771983423143936',
                'name' => 'WE DEM BOYZZ!!!!!!',
            ]),
            41771983423143937 => new DiscordRole([
                'id'   => '41771983423143937',
                'name' => 'TEST ROLE',
            ]),
            41771983423143939 => new DiscordRole([
                'id'   => '41771983423143939',
                'name' => 'ANOTHER ROLE',
            ]),
        ];

        $driver = DiscordClient::getInstance();

        $this->assertEquals($artifact, $driver->getSets());
    }

    public function testGetSingleSet()
    {
        $settings = (object) [
            'client_id' => 123456890,
            'client_secret' => 'abcde-4fs8s7f51sq654g',
            'bot_token' => 'sdf4df6g.es4dfg97f.ze978dfg49fh4fg4',
            'guild_id' => 41771983423143937,
            'owner_id' => 80351110224678912,
        ];

        setting(['seat-connector.drivers.discord', $settings], true);

        config([
            'discord-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/roles.json')),
            ],
        ]);

        $set_id = '41771983423143937';
        $artifact = new DiscordRole([
            'id'   => $set_id,
            'name' => 'TEST ROLE',
        ]);

        $driver = DiscordClient::getInstance();

        $this->assertEquals($artifact, $driver->getSet($set_id));
    }
}
