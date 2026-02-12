<?php

declare(strict_types=1);

namespace Kemo\Beatport\Dto;

final readonly class LoginResult
{
    public function __construct(
        public string $sessionCookies,
        public string $username,
        public ?string $accessToken = null,
        public ?string $refreshToken = null,
        public ?int $expiresIn = null,
        public ?string $beatportUserId = null,
    ) {
    }
}
