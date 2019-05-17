<?php
/**
 * User: Warlof Tutsimo <loic.leuilliot@gmail.com>
 * Date: 01/07/2018
 * Time: 12:58
 */

namespace Warlof\Seat\Connector\Discord\Caches;

use Illuminate\Support\Facades\Redis;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RestCord\RateLimit\Provider\AbstractRateLimitProvider;

class RedisRateLimitProvider extends AbstractRateLimitProvider
{
    const MAX_TTL = 60 * 60 * 24 * 7;

    /**
     * @param RequestInterface $request
     * @return string
     */
    public function getKey($key)
    {
        return sprintf('seat.discord.connector.ratelimit.%s', $key);
    }

    /**
     * Returns when the last request was made.
     *
     * @param RequestInterface $request
     *
     * @return float|null When the last request was made.
     */
    public function getLastRequestTime(RequestInterface $request)
    {
        $route = $this->getRoute($request);
        $key = $this->getKey(sprintf('%s.lastRequest', 'api'));

        return Redis::exists($key) ? Redis::get($key) : null;
    }

    /**
     * Used to set the current time as the last request time to be queried when
     * the next request is attempted.
     *
     * @param RequestInterface $request
     */
    public function setLastRequestTime(RequestInterface $request)
    {
        $route = $this->getRoute($request);
        $key = $this->getKey(sprintf('%s.lastRequest', 'api'));

        Redis::setex($key, static::MAX_TTL, $this->getRequestTime($request));
    }

    /**
     * Returns the minimum amount of time that is required to have passed since
     * the last request was made. This value is used to determine if the current
     * request should be delayed, based on when the last request was made.
     *
     * Returns the allowed  between the last request and the next, which
     * is used to determine if a request should be delayed and by how much.
     *
     * @param RequestInterface $request The pending request.
     *
     * @return float The minimum amount of time that is required to have passed
     *               since the last request was made (in microseconds).
     */
    public function getRequestAllowance(RequestInterface $request)
    {
        $route = $this->getRoute($request);
        $key = $this->getKey(sprintf('%s.reset', 'api'));

        if (! Redis::exists($key))
            return 0;

        return (Redis::get($key) - time()) * 1000000;
    }

    /**
     * Used to set the minimum amount of time that is required to pass between
     * this request and the next (in microseconds).
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response The resolved response.
     */
    public function setRequestAllowance(RequestInterface $request, ResponseInterface $response)
    {
        $route = $this->getRoute($request);
        $key = $this->getKey(sprintf('%s.reset', 'api'));

        $remaining = $response->getHeaderLine('X-RateLimit-Remaining');
        $reset     = $response->getHeaderLine('X-RateLimit-Reset');

        if (empty($remaining) || empty($reset) || (int) $remaining[0] > 0)
            return;

        Redis::set($key, static::MAX_TTL, $reset[0]);
    }
}
