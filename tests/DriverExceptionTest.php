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
use Warlof\Seat\Connector\Drivers\Discord\Tests\Fetchers\TestFetcher;
use Warlof\Seat\Connector\Exceptions\DriverException;
use Warlof\Seat\Connector\Exceptions\DriverSettingsException;
use Warlof\Seat\Connector\Exceptions\InvalidDriverIdentityException;

/**
 * Class Test.
 *
 * @package Warlof\Seat\Connector\Drivers\Discord\Tests
 */
class DriverExceptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/migrations');
    }

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

    public function testUnsetSettingsDriverException()
    {
        $this->expectException(DriverSettingsException::class);
        $this->expectExceptionMessage('The Driver has not been configured yet.');

        DiscordClient::getInstance();
    }

    public function testGuildIdDriverSettingException()
    {
        setting([
            'seat-connector.drivers.discord', (object) [
                'guild_id' => null,
            ],
        ], true);

        $this->expectException(DriverSettingsException::class);
        $this->expectExceptionMessage('Parameter guild_id is missing.');

        DiscordClient::getInstance();
    }

    public function testBotTokenDriverSettingException()
    {
        setting([
            'seat-connector.drivers.discord', (object) [
                'guild_id' => 41771983423143937,
            ],
        ], true);

        $this->expectException(DriverSettingsException::class);
        $this->expectExceptionMessage('Parameter bot_token is missing.');

        DiscordClient::getInstance();
    }

    public function testGetSetsException()
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
                new Response(403),
            ],
        ]);

        $this->expectException(DriverException::class);

        DiscordClient::getInstance()->getSets();
    }

    public function testGetSingleSetException()
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
                new Response(404),
            ],
        ]);

        $this->expectException(DriverException::class);

        DiscordClient::getInstance()->getSet('685498746416313');
    }

    public function testGetUsersGuzzleException()
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
                new Response(400),
            ],
        ]);

        $this->expectException(DriverException::class);

        DiscordClient::getInstance()->getUsers();
    }

    public function testGetSingleUserDriverSettingsException()
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
                new Response(400, [], '{"code": 10004}'),
            ],
        ]);

        $this->expectException(DriverSettingsException::class);
        $this->expectExceptionMessage('Configured Guild ID 41771983423143937 is invalid.');

        DiscordClient::getInstance()->getUser('65416847987984664');
    }

    public function testGetSingleUserInvalidDriverIdentityException()
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
                new Response(404, [], '{"code": 10007}'),
            ],
        ]);

        $this->expectException(InvalidDriverIdentityException::class);
        $this->expectExceptionMessage('User ID 65416847987984664 is not found in Guild 41771983423143937.');

        DiscordClient::getInstance()->getUser('65416847987984664');
    }

    public function testGetSingleUserClientException()
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
                new Response(403),
            ],
        ]);

        $this->expectException(DriverException::class);

        DiscordClient::getInstance()->getUser('65416847987984664');
    }

    public function testGetSingleUserGuzzleException()
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
                new Response(500),
            ],
        ]);

        $this->expectException(DriverException::class);

        DiscordClient::getInstance()->getUser('65416847987984664');
    }
}
