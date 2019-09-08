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

namespace Warlof\Seat\Connector\Drivers\Discord\Caches;

use GuzzleHttp\Psr7\Uri;
use Illuminate\Support\Facades\Redis;
use Psr\Http\Message\ResponseInterface;

class RedisRateLimitProvider
{
    const MAX_TTL = 60 * 60 * 24 * 7;

    /**
     * Used to set the current time as the last request time to be queried when
     * the next request is attempted.
     *
     * @param \GuzzleHttp\Psr7\Uri $uri
     */
    public function setLastRequestTime(Uri $uri)
    {
        $key = $this->getKey(sprintf('%s.lastRequest', sha1($uri->getPath())));

        Redis::setex($key, static::MAX_TTL, $this->getRequestTime());
    }

    /**
     * Returns the minimum amount of time that is required to have passed since
     * the last request was made. This value is used to determine if the current
     * request should be delayed, based on when the last request was made.
     *
     * Returns the allowed  between the last request and the next, which
     * is used to determine if a request should be delayed and by how much.
     *
     * @param \GuzzleHttp\Psr7\Uri $uri
     * @return float The minimum amount of time that is required to have passed
     *               since the last request was made (in microseconds).
     */
    public function getRequestAllowance(Uri $uri)
    {
        $key = $this->getKey(sprintf('%s.reset', sha1($uri->getPath())));

        if (! Redis::exists($key))
            return 0;

        return (Redis::get($key) - $this->getLastRequestTime(new Uri($uri)));
    }

    /**
     * Used to set the minimum amount of time that is required to pass between
     * this request and the next (in microseconds).
     *
     * @param \GuzzleHttp\Psr7\Uri $uri
     * @param ResponseInterface $response The resolved response.
     */
    public function setRequestAllowance(Uri $uri, ResponseInterface $response)
    {
        $key = $this->getKey(sprintf('%s.reset', sha1($uri->getPath())));

        $remaining = $response->getHeaderLine('X-RateLimit-Remaining');
        $reset     = $response->getHeaderLine('X-RateLimit-Reset');

        if (empty($remaining) || empty($reset) || (int) $remaining[0] > 1)
            return;

        Redis::set($key, static::MAX_TTL, $reset[0]);
    }

    /**
     * @param $key
     * @return string
     */
    private function getKey($key)
    {
        return sprintf('seat:seat-connector.drivers.discord.%s', $key);
    }

    /**
     * Returns what is considered the time when a given request is being made.
     *
     * @return float Time when the given request is being made.
     */
    private function getRequestTime()
    {
        return carbon()->setTimezone('UTC')->getTimestamp();
    }

    /**
     * Returns when the last request was made.
     *
     * @param Uri $uri
     * @return float|null When the last request was made.
     */
    private function getLastRequestTime(Uri $uri)
    {
        $key = $this->getKey(sprintf('%s.lastRequest', sha1($uri->getPath())));

        return Redis::exists($key) ? Redis::get($key) : null;
    }
}
