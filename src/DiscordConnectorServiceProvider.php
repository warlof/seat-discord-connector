<?php
/**
 * This file is part of SeAT Discord Connector.
 *
 * Copyright (C) 2019  Warlof Tutsimo <loic.leuilliot@gmail.com>
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

namespace Warlof\Seat\Connector\Drivers\Discord;

use App\Providers\AbstractSeatPlugin;
use Illuminate\Support\Facades\Event;

/**
 * Class DiscordConnectorServiceProvider.
 *
 * @package Warlof\Seat\Connector\Drivers\Discord
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
        $this->addRoutes();
        $this->addTranslations();

        $this->registerSocialiteDiscordDriver();
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
            __DIR__ . '/Config/seat-connector.config.php', 'seat-connector.drivers.discord');

        // set default configuration for Discord driver to make Socialite happy if none set
        if (! $this->app['config']->get('services.discord')) {
            $this->app['config']->set('services.discord', [
                'client_id'     => '',
                'client_secret' => '',
                'redirect'      => '',
            ]);
        }
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
     * Import translations
     */
    private function addTranslations()
    {
        $this->loadTranslationsFrom(__DIR__ . '/lang', 'seat-connector-discord');
    }

    /**
     * Register Socialite Discord Driver
     */
    private function registerSocialiteDiscordDriver()
    {
        Event::listen(\SocialiteProviders\Manager\SocialiteWasCalled::class, 'SocialiteProviders\\Discord\\DiscordExtendSocialite@handle');
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
