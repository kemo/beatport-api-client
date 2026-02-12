<?php

declare(strict_types=1);

namespace Beatport\Exception;

use RuntimeException;

class NotConnectedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Not connected to Beatport');
    }
}
