<?php
/**
 * This file is part of discord-connector and provides user synchronization between both SeAT and a Discord Guild
 *
 * Copyright (C) 2016, 2017, 2018  Loïc Leuilliot <loic.leuilliot@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Warlof\Seat\Connector\Discord;

use Illuminate\Support\ServiceProvider;
use RestCord\DiscordClient;
use Warlof\Seat\Connector\Discord\Commands\DiscordLogsClear;
use Warlof\Seat\Connector\Discord\Commands\DiscordRoleSync;
use Warlof\Seat\Connector\Discord\Commands\DiscordUserPolicy;
use Warlof\Seat\Connector\Discord\Commands\DiscordUserTerminator;

/**
 * Class DiscordConnectorServiceProvider
 * @package Warlof\Seat\Connector\Discord
 */
class DiscordConnectorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->addCommands();
        $this->addRoutes();
        $this->addViews();
        $this->addPublications();
        $this->addTranslations();
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/Config/discord-connector.config.php', 'discord-connector.config');

        $this->mergeConfigFrom(
            __DIR__ . '/Config/discord-connector.permissions.php', 'web.permissions');
        
        $this->mergeConfigFrom(
            __DIR__ . '/Config/package.sidebar.php', 'package.sidebar');

        // push discord client into container as singleton if token has been set
        $bot_token = setting('warlof.discord-connector.credentials.bot_token', true);

        if (! is_null($bot_token)) {
            $this->app->singleton('discord', function () {
                return new DiscordClient([
                    'tokenType' => 'Bot',
                    'token' => setting('warlof.discord-connector.credentials.bot_token', true),
                ]);
            });
        }

        // bind discord alias to DiscordClient
        $this->app->alias('discord', DiscordClient::class);
    }

    /**
     * Register cli commands
     */
    private function addCommands()
    {
        $this->commands([
        	DiscordLogsClear::class,
	        DiscordUserPolicy::class,
            DiscordUserTerminator::class,
            DiscordRoleSync::class,
        ]);
    }

    /**
     * Import translations
     */
    private function addTranslations()
    {
        $this->loadTranslationsFrom(__DIR__ . '/lang', 'discord-connector');
    }

    /**
     * Import routes
     */
    private function addRoutes()
    {
        if (! $this->app->routesAreCached()) {
            include __DIR__ . '/Http/routes.php';
        }
    }

    /**
     * Register views
     */
    private function addViews()
    {
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'discord-connector');
    }

    /**
     * Import migration and static content
     */
    private function addPublications()
    {
        $this->publishes([
            __DIR__ . '/database/migrations/' => database_path('migrations'),
            __DIR__ . '/resources/assets/css/' => public_path('web/css'),
        ]);
    }
}
