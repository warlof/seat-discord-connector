<?php
/**
 * This file is part of slackbot and provide user synchronization between both SeAT and a Slack Team
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
    'namespace' => 'Warlof\Seat\Connector\Discord\Http\Controllers',
    'prefix' => 'discord-connector'
], function() {

    Route::group([
        'middleware' => 'web'
    ], function() {

        Route::get('/server/join', [
            'as' => 'discord-connector.server.join',
            'uses' => 'Services\ServerController@join',
        ]);

        Route::get('/server/callback', [
            'as' => 'discord-connector.server.callback',
            'uses' => 'Services\ServerController@callback'
        ]);

        // Endpoints with Configuration Permission
        Route::group([
            'middleware' => 'bouncer:discord-connector.setup'
        ], function() {

            Route::get('/configuration', [
                'as' => 'discord-connector.configuration',
                'uses' => 'DiscordSettingsController@getConfiguration'
            ]);

            Route::get('/run/{commandName}', [
                'as' => 'discord-connector.command.run',
                'uses' => 'DiscordSettingsController@getSubmitJob'
            ]);

            // OAuth
            Route::group([
                'namespace' => 'Services',
                'prefix' => 'oauth'
            ], function() {

                Route::post('/configuration', [
                    'as' => 'discord-connector.oauth.configuration.post',
                    'uses' => 'OAuthController@postConfiguration'
                ]);

                Route::get('/callback', [
                    'as' => 'discord-connector.oauth.callback',
                    'uses' => 'OAuthController@callback'
                ]);

            });

        });

        Route::group([
            'middleware' => 'bouncer:discord-connector.create'
        ], function() {

            Route::get('/public/{channel_id}/remove', [
                'as' => 'discord-connector.public.remove',
                'uses' => 'DiscordJsonController@getRemovePublic',
                'middleware' => 'bouncer:discord-connector.create'
            ]);

            Route::get('/users/{group_id}/{channel_id}/remove', [
                'as' => 'discord-connector.user.remove',
                'uses' => 'DiscordJsonController@getRemoveUser',
                'middleware' => 'bouncer:discord-connector.create'
            ]);

            Route::get('/roles/{role_id}/{channel_id}/remove', [
                'as' => 'discord-connector.role.remove',
                'uses' => 'DiscordJsonController@getRemoveRole',
                'middleware' => 'bouncer:discord-connector.create'
            ]);

            Route::get('/corporations/{corporation_id}/{channel_id}/remove', [
                'as' => 'discord-connector.corporation.remove',
                'uses' => 'DiscordJsonController@getRemoveCorporation',
                'middleware' => 'bouncer:discord-connector.create'
            ]);

            Route::get('/corporation/{corporation_id}/{title_id}/{channel_id}/remove', [
                'as' => 'discord-connector.title.remove',
                'uses' => 'DiscordJsonController@getRemoveTitle',
                'middleware' => 'bouncer:discord-connector:create'
            ]);

            Route::get('/alliances/{alliance_id}/{channel_id}/remove', [
                'as' => 'discord-connector.alliance.remove',
                'uses' => 'DiscordJsonController@getRemoveAlliance',
                'middleware' => 'bouncer:discord-connector.create'
            ]);

            Route::post('/', [
                'as' => 'discord-connector.add',
                'uses' => 'DiscordJsonController@postRelation',
                'middleware' => 'bouncer:discord-connector.create'
            ]);

        });

        Route::get('/', [
            'as' => 'discord-connector.list',
            'uses' => 'DiscordJsonController@getRelations',
            'middleware' => 'bouncer:discord-connector.view'
        ]);

        Route::get('/logs', [
            'as' => 'discord-connector.logs',
            'uses' => 'DiscordLogsController@getLogs',
            'middleware' => 'bouncer:discord-connector.security'
        ]);

        Route::get('/users', [
            'as' => 'discord-connector.users',
            'uses' => 'DiscordController@getUsers',
            'middleware' => 'bouncer:discord-connector.view'
        ]);

        Route::group([
            'prefix' => 'json'
        ], function() {

            Route::get('/logs', [
                'as' => 'discord-connector.json.logs',
                'uses' => 'DiscordLogsController@getJsonLogData',
                'middleware' => 'bouncer:discord-connector.security'
            ]);

            Route::get('/users', [
                'as' => 'discord-connector.json.users',
                'uses' => 'DiscordController@getUsersData',
                'middleware' => 'bouncer:discord-connector.view'
            ]);

            Route::post('/user/remove', [
                'as' => 'discord-connector.json.user.remove',
                'uses' => 'DiscordController@postRemoveUserMapping',
                'middleware' => 'bouncer:discord-connector.security'
            ]);

            Route::get('/users/channels', [
                'as' => 'discord-connector.json.user.roles',
                'uses' => 'DiscordJsonController@getJsonUserRolesData',
                'middleware' => 'bouncer:discord-connector.security'
            ]);

            Route::get('/titles', [
                'as' => 'discord-connector.json.titles',
                'uses' => 'DiscordJsonController@getJsonTitle',
                'middleware' => 'bouncer:discord-connector.create'
            ]);

        });

    });

});
