<?php

return [
    'name'     => 'discord',
    'icon'     => 'fa-gamepad',
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
    ],
];
