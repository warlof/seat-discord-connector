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

use RestCord\DiscordClient;
use Seat\Services\AbstractSeatPlugin;
use Warlof\Seat\Connector\Discord\Caches\RedisRateLimitProvider;
use Warlof\Seat\Connector\Discord\Commands\DiscordLogsClear;
use Warlof\Seat\Connector\Discord\Commands\DiscordRoleSync;
use Warlof\Seat\Connector\Discord\Commands\DiscordUserPolicy;
use Warlof\Seat\Connector\Discord\Commands\DiscordUserTerminator;

/**
 * Class DiscordConnectorServiceProvider
 * @package Warlof\Seat\Connector\Discord
 */
class DiscordConnectorServiceProvider extends AbstractSeatPlugin
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
        $this->addMigrations();
        $this->addPublications();
        $this->addTranslations();

        $this->addDiscordContainer();
        $this->configureApi();
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
     * Import migrations
     */
    private function addMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations/');
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
            __DIR__ . '/resources/assets/css/' => public_path('web/css'),
        ]);
    }

    private function addDiscordContainer()
    {
        // push discord client into container as singleton if token has been set
        $bot_token = setting('warlof.discord-connector.credentials.bot_token', true);

        if (! is_null($bot_token)) {
            $this->app->singleton('discord', function () {
                return new DiscordClient([
                    'tokenType'         => 'Bot',
                    'token'             => setting('warlof.discord-connector.credentials.bot_token', true),
                    'rateLimitProvider' => new RedisRateLimitProvider([
                        'throwOnRatelimit' => false,
                    ]),
                ]);
            });
        }

        // bind discord alias to DiscordClient
        $this->app->alias('discord', DiscordClient::class);
    }

    private function configureApi()
    {
        // ensure current annotations setting is an array of path or transform into it
        $current_annotations = config('l5-swagger.paths.annotations');
        if (! is_array($current_annotations))
            $current_annotations = [$current_annotations];

        // merge paths together and update config
        config([
            'l5-swagger.paths.annotations' => array_unique(array_merge($current_annotations, [
                __DIR__ . '/Models',
                __DIR__ . '/Http/Controllers/Api/v1',
            ])),
        ]);
    }

    /**
     * Return the plugin public name as it should be displayed into settings.
     *
     * @example SeAT Web
     *
     * @return string
     */
    public function getName(): string
    {
        return 'Discord Connector';
    }

    /**
     * Return the plugin repository address.
     *
     * @example https://github.com/eveseat/web
     *
     * @return string
     */
    public function getPackageRepositoryUrl(): string
    {
        return 'https://github.com/warlof/seat-discord-connector';
    }

    /**
     * Return the plugin technical name as published on package manager.
     *
     * @example web
     *
     * @return string
     */
    public function getPackagistPackageName(): string
    {
        return 'seat-discord-connector';
    }

    /**
     * Return the plugin vendor tag as published on package manager.
     *
     * @example eveseat
     *
     * @return string
     */
    public function getPackagistVendorName(): string
    {
        return 'warlof';
    }

    /**
     * Return the plugin installed version.
     *
     * @return string
     */
    public function getVersion(): string
    {
        return config('discord-connector.config.version');
    }
}
