<?php

declare(strict_types=1);

namespace Beatport\Exception;

use RuntimeException;

class TrackNotFoundException extends RuntimeException
{
    public function __construct(int $trackId)
    {
        parent::__construct(sprintf('Track %d not found', $trackId));
    }
}
