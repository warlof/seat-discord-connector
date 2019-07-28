<?php

namespace Warlof\Seat\Connector\Discord\Drivers\Teamspeak\Exceptions;

use Throwable;

abstract class TeamspeakException extends \Exception
{
    public function __construct(array $errors = [], $code = 0, Throwable $previous = null)
    {
        parent::__construct(implode("\r\n", $errors), $code, $previous);
    }
}
