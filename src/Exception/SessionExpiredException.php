<?php

declare(strict_types=1);

namespace Kemo\Beatport\Exception;

use RuntimeException;

class SessionExpiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Beatport session has expired. Please provide new session cookies.');
    }
}
