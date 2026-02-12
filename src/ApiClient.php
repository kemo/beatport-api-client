<?php

declare(strict_types=1);

namespace Kemo\Beatport;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiClient
{
    private const API_BASE_URL = 'https://api.beatport.com/v4';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Get user's My Beatport library tracks.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMyBeatportTracks(string $accessToken, int $perPage = 100): array
    {
        $tracks = [];
        $page = 1;

        do {
            $response = $this->request($accessToken, 'GET', '/my/beatport/tracks/', [
                'page' => $page,
                'per_page' => $perPage,
            ]);

            foreach ($response['results'] ?? [] as $track) {
                $tracks[] = $track;
            }

            $hasMore = ($response['next'] ?? null) !== null;
            ++$page;
        } while ($hasMore);

        return $tracks;
    }

    /**
     * Get track details by ID.
     *
     * @return array<string, mixed>
     */
    public function getTrack(string $accessToken, int $trackId): array
    {
        return $this->request($accessToken, 'GET', '/catalog/tracks/' . $trackId . '/');
    }

    /**
     * Get user account information.
     *
     * @return array<string, mixed>
     */
    public function getAccount(string $accessToken): array
    {
        return $this->request($accessToken, 'GET', '/my/account/');
    }

    /**
     * Search for tracks.
     *
     * @return array<string, mixed>
     */
    public function searchTracks(string $accessToken, string $query, int $page = 1, int $perPage = 25): array
    {
        return $this->request($accessToken, 'GET', '/catalog/search/', [
            'q' => $query,
            'type' => 'tracks',
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * Get top tracks chart.
     *
     * @return array<string, mixed>
     */
    public function getTopTracks(string $accessToken, int $page = 1, int $perPage = 25): array
    {
        return $this->request($accessToken, 'GET', '/catalog/tracks/', [
            'page' => $page,
            'per_page' => $perPage,
            'order_by' => '-publish_date',
        ]);
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    private function request(
        string $accessToken,
        string $method,
        string $endpoint,
        array $query = [],
    ): array {
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ],
        ];

        if (!empty($query)) {
            $options['query'] = $query;
        }

        $response = $this->httpClient->request($method, self::API_BASE_URL . $endpoint, $options);

        return $response->toArray();
    }
}
