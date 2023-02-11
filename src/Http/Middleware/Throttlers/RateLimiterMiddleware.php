<?php

/**
 * This file is part of SeAT Discord Connector.
 *
 * Copyright (C) 2020  Warlof Tutsimo <loic.leuilliot@gmail.com>
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

namespace Warlof\Seat\Connector\Drivers\Discord\Http\Middleware\Throttlers;

use Illuminate\Support\Facades\Redis;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class RateLimiterMiddleware.
 *
 * @package Warlof\Seat\Connector\Drivers\Discord\Http\Middleware\Throttlers
 */
class RateLimiterMiddleware
{
    const MAP_ENDPOINTS = [
        '/guilds/{guild.id}' => '/\/api\/guilds\/[0-9]+/i',
        '/guilds/{guild.id}/members' => '/\/api\/guilds\/[0-9]+\/members/i',
        '/guilds/{guild.id}/members/{user.id}' => '/\/api\/guilds\/[0-9]+\/members\/[0-9]+/i',
        '/guilds/{guild.id}/members/{user.id}/roles/{role.id}' => '/\/api\/guilds\/[0-9]+\/members\/[0-9]+\/roles\/[0-9]+/i',
        '/guilds/{guild.id}/roles' => '/\/api\/guilds\/[0-9]+\/roles/i',
    ];

    const REDIS_CACHE_PREFIX = 'seat:seat-connector.drivers.discord';

    /**
     * @param callable $handler
     * @return \Closure
     */
    public function __invoke(callable $handler)
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            // determine request timestamp
            $now = time();

            // retrieve throttler metadata for requested endpoint
            $key = $this->getCacheKey($request->getUri());
            $metadata = Redis::get($key) ?: null;

            if (! is_null($metadata)) {
                $metadata = unserialize($metadata);

                // compute delay between reset time and current time
                // add 10 seconds to the result in order to avoid server clock issues
                // furthermore, the limit is removed after the exact time
                $delay = $metadata->reset + 10 - $now;

                // in case limit is near to be reached, we pause the request for computed duration
                if ($metadata->remaining < 2 && $delay > 0)
                    sleep($delay);
            }

            // send the request and retrieve response
            $promise = $handler($request, $options);

            return $promise->then(function (ResponseInterface $response) use ($key) {

                // update cache entry for the endpoint using new RateLimit / RateReset values
                $metadata = $this->getEndpointMetadata($response);
                Redis::setex($key, 60 * 60 * 24 * 7, serialize($metadata));

                // forward response to the stack
                return $response;
            });
        };
    }

    /**
     * @param \Psr\Http\Message\UriInterface $uri
     * @param string $type
     * @return string
     */
    private function getCacheKey(UriInterface $uri)
    {
        $match_pattern = $uri->getPath();

        // attempt to resolve the requested endpoint
        foreach (self::MAP_ENDPOINTS as $endpoint => $pattern) {
            if (preg_match($pattern, $uri->getPath()) === 1)
                $match_pattern = $endpoint;
        }

        // generate a hash based on the endpoint
        $hash = sha1($match_pattern);

        // return a cache key built using prefix, hash and requested type
        return sprintf('%s.%s.metadata', self::REDIS_CACHE_PREFIX, $hash);
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return object
     */
    private function getEndpointMetadata(ResponseInterface $response)
    {
        $remaining = intval($response->getHeaderLine('X-RateLimit-Remaining')) ?: 0;
        $reset = intval($response->getHeaderLine('X-RateLimit-Reset')) ?: 0;

        return (object) [
            'reset' => $reset,
            'remaining' => $remaining,
        ];
    }
}
