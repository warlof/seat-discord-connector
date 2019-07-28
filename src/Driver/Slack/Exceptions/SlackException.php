<?php

namespace Warlof\Seat\Connector\Drivers\Slack\Exceptions;

use Throwable;

/**
 * Class SlackException.
 *
 * @package Warlof\Seat\Connector\Drivers\Slack\Exceptions
 */
class SlackException extends \Exception
{
    /**
     * SlackException constructor.
     *
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
