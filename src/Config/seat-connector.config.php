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

return [
    'name'     => 'discord',
    'icon'     => 'fab fa-discord',
    'client'   => \Warlof\Seat\Connector\Drivers\Discord\Driver\DiscordClient::class,
    'settings' => [
        [
            'name'  => 'client_id',
            'label' => 'seat-connector-discord::seat.client_id',
            'type'  => 'text',
        ],
        [
            'name'  => 'client_secret',
            'label' => 'seat-connector-discord::seat.client_secret',
            'type'  => 'text',
        ],
        [
            'name'  => 'bot_token',
            'label' => 'seat-connector-discord::seat.bot_token',
            'type'  => 'text',
        ],
        [
            'name'  => 'use_email_scope',
            'label' => 'seat-connector-discord::seat.use_email_scope',
            'type'  => 'checkbox',
        ],
        [
            'name'  => 'visible_roles',
            'label' => 'seat-connector-discord::seat.visible_roles',
            'type'  => 'text',
        ],
        [
            'name'  => 'can_add_roles',
            'label' => 'seat-connector-discord::seat.can_add_roles',
            'type'  => 'text',
        ],
        [
            'name'  => 'can_remove_roles',
            'label' => 'seat-connector-discord::seat.can_remove_roles',
            'type'  => 'text',
        ],
    ],
];
