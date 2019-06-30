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

Route::group([
    'namespace' => 'Warlof\Seat\Connector\Discord\Http\Controllers\Api\v1',
    'prefix' => 'api',
    'middleware' => 'api.auth'
], function() {
    Route::group(['prefix' => 'v2'], function () {
        Route::group(['prefix' => 'discord-connector'], function () {
                Route::get('/mapping', 'DiscordApiController@getDiscordMappings');
        });
    });
});

Route::group([
    'namespace' => 'Warlof\Seat\Connector\Discord\Http\Controllers',
    'prefix' => 'discord-connector'
], function() {

    Route::group([
        'middleware' => ['web', 'auth', 'locale'],
    ], function() {

        Route::group([
            'middleware' => 'bouncer:discord-connector.view',
        ], function () {

            Route::get('/server/join', [
                'as' => 'discord-connector.server.join',
                'uses' => 'Services\ServerController@join',
            ]);

            Route::get('/server/callback', [
                'as' => 'discord-connector.server.callback',
                'uses' => 'Services\ServerController@callback',
            ]);

        });

        // Endpoints with Configuration Permission
        Route::group([
            'middleware' => 'bouncer:discord-connector.setup',
        ], function() {

            Route::get('/configuration', [
                'as' => 'discord-connector.configuration',
                'uses' => 'DiscordSettingsController@getConfiguration',
            ]);

            Route::post('/run', [
                'as'   => 'discord-connector.command.run',
                'uses' => 'DiscordSettingsController@submitJob',
            ]);

            // OAuth
            Route::group([
                'namespace' => 'Services',
                'prefix' => 'oauth'
            ], function() {

                Route::post('/configuration', [
                    'as' => 'discord-connector.oauth.configuration.post',
                    'uses' => 'OAuthController@postConfiguration',
                ]);

                Route::get('/callback', [
                    'as' => 'discord-connector.oauth.callback',
                    'uses' => 'OAuthController@callback',
                ]);

            });

        });

        Route::group([
            'middleware' => 'bouncer:discord-connector.create',
        ], function() {

            Route::delete('/public/{channel_id}', [
                'as'   => 'discord-connector.public.remove',
                'uses' => 'DiscordJsonController@removePublic',
            ]);

            Route::delete('/users/{group_id}/{channel_id}', [
                'as'   => 'discord-connector.user.remove',
                'uses' => 'DiscordJsonController@removeUser',
            ]);

            Route::delete('/roles/{role_id}/{channel_id}', [
                'as'   => 'discord-connector.role.remove',
                'uses' => 'DiscordJsonController@removeRole',
            ]);

            Route::delete('/corporations/{corporation_id}/{channel_id}', [
                'as'   => 'discord-connector.corporation.remove',
                'uses' => 'DiscordJsonController@removeCorporation',
            ]);

            Route::delete('/corporation/{corporation_id}/{title_id}/{channel_id}', [
                'as'   => 'discord-connector.title.remove',
                'uses' => 'DiscordJsonController@removeTitle',
            ]);

            Route::delete('/alliances/{alliance_id}/{channel_id}', [
                'as'   => 'discord-connector.alliance.remove',
                'uses' => 'DiscordJsonController@removeAlliance',
            ]);

            Route::post('/', [
                'as'   => 'discord-connector.add',
                'uses' => 'DiscordJsonController@postRelation',
            ]);

        });

        Route::group([
            'middleware' => 'bouncer:discord-connector.security',
        ], function() {

            Route::get('/', [
                'as' => 'discord-connector.list',
                'uses' => 'DiscordJsonController@getRelations',
            ]);

            Route::get('/logs', [
                'as' => 'discord-connector.logs',
                'uses' => 'DiscordLogsController@getLogs',
            ]);

            Route::get('/users', [
                'as' => 'discord-connector.users',
                'uses' => 'DiscordController@getUsers',
            ]);

            Route::group([
                'prefix' => 'json',
            ], function () {

                Route::get('/logs', [
                    'as' => 'discord-connector.json.logs',
                    'uses' => 'DiscordLogsController@getJsonLogData',
                ]);

                Route::get('/users', [
                    'as' => 'discord-connector.json.users',
                    'uses' => 'DiscordController@getUsersData',
                ]);

                Route::delete('/user', [
                    'as'   => 'discord-connector.json.user.remove',
                    'uses' => 'DiscordController@removeUserMapping',
                ]);

                Route::get('/users/channels', [
                    'as' => 'discord-connector.json.user.roles',
                    'uses' => 'DiscordJsonController@getJsonUserRolesData',
                ]);

            });
        });

        Route::get('/titles', [
            'as' => 'discord-connector.json.titles',
            'uses' => 'DiscordJsonController@getJsonTitle',
            'middleware' => 'bouncer:discord-connector.create',
        ]);

    });

});
