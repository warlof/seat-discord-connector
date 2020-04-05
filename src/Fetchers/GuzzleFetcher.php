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

namespace Warlof\Seat\Connector\Drivers\Discord\Fetchers;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\ResponseInterface;
use Warlof\Seat\Connector\Drivers\Discord\Http\Middleware\Throttlers\RateLimiterMiddleware;

/**
 * Class GuzzleFetcher.
 *
 * @package Warlof\Seat\Connector\Drivers\Discord\Fetchers
 */
class GuzzleFetcher implements IFetcher
{
    /**
     * @var \GuzzleHttp\Client
     */
    private $client;

    /**
     * GuzzleFetcher constructor.
     *
     * @param string $base_uri
     * @param string $token
     */
    public function __construct(string $base_uri, string $token)
    {
        $stack = HandlerStack::create();
        $stack->push(new RateLimiterMiddleware());

        $this->client = new Client([
            'base_uri' => $base_uri,
            'headers' => [
                'Authorization' => sprintf('Bot %s', $token),
                'Content-Type' => 'application/json',
                'User-Agent' => sprintf('warlof@seat-discord-connector/%s GitHub SeAT', config('discord-connector.config.version')),
            ],
            'handler' => $stack,
        ]);
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array $options
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function request(string $method, string $uri = '', array $options = []): ResponseInterface
    {
        return $this->client->request($method, $uri, $options);
    }
}
