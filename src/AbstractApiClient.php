<?php

declare(strict_types=1);

namespace Kemo\Beatport;

use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class AbstractApiClient
{
    public function __construct(
        protected readonly HttpClientInterface $httpClient,
    ) {
    }

    abstract protected function getBaseUrl(): string;

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>
     */
    protected function request(string $method, string $endpoint, array $query = [], array $headers = []): array
    {
        $options = [
            'headers' => array_merge(['Accept' => 'application/json'], $headers),
        ];

        if (!empty($query)) {
            $options['query'] = $query;
        }

        return $this->httpClient->request($method, $this->getBaseUrl() . $endpoint, $options)->toArray();
    }
}
