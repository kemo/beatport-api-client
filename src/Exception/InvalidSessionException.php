<?php

declare(strict_types=1);

namespace Beatport\Exception;

use RuntimeException;

class InvalidSessionException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Invalid session cookies. Please ensure you copied the full cookie string.');
    }
}
