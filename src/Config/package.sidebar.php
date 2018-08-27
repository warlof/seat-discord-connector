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

return [
    'discord-connector' => [
        'name'          => 'Discord Connector',
        'icon'          => 'fa-plug',
        'route_segment' => 'discord-connector',
        'entries' => [
            [
                'name'  => 'Connect',
                'label' => 'discord-connector::seat.join',
                'icon'  => 'fa-sign-in',
                'route' => 'discord-connector.server.join',
                'permission' => 'discord-connector.view',
            ],
            [
                'name'  => 'Access Management',
                'label' => 'web::seat.access',
                'icon'  => 'fa-shield',
                'route' => 'discord-connector.list',
                'permission' => 'discord-connector.security'
            ],
            [
                'name'  => 'User Mapping',
                'label' => 'discord-connector::seat.user_mapping',
                'icon'  => 'fa-exchange',
                'route' => 'discord-connector.users',
                'permission' => 'discord-connector.security'
            ],
            [
                'name'       => 'Settings',
                'label'      => 'web::seat.configuration',
                'icon'       => 'fa-cogs',
                'route'      => 'discord-connector.configuration',
                'permission' => 'discord-connector.setup'
            ],
            [
                'name'   => 'Logs',
                'label'  => 'web::seat.log',
                'plural' => true,
                'icon'   => 'fa-list',
                'route'  => 'discord-connector.logs',
                'permission' => 'discord-connector.security'
            ],
        ],
        'permission' => 'discord-connector.view'
    ],
];
