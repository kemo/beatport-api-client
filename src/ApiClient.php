<?php

declare(strict_types=1);

namespace Kemo\Beatport;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ApiClient extends AbstractApiClient
{
    public function __construct(
        HttpClientInterface $httpClient,
        private readonly string $accessToken,
    ) {
        parent::__construct($httpClient);
    }

    protected function getBaseUrl(): string
    {
        return Beatport::API_BASE_URL;
    }

    /**
     * Get user's My Beatport library tracks.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMyBeatportTracks(int $perPage = 100): array
    {
        $tracks = [];
        $page = 1;

        do {
            $response = $this->authenticatedRequest('GET', '/my/beatport/tracks/', [
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
    public function getTrack(int $trackId): array
    {
        return $this->authenticatedRequest('GET', '/catalog/tracks/' . $trackId . '/');
    }

    /**
     * Get user account information.
     *
     * @return array<string, mixed>
     */
    public function getAccount(): array
    {
        return $this->authenticatedRequest('GET', '/my/account/');
    }

    /**
     * Search for tracks.
     *
     * @return array<string, mixed>
     */
    public function searchTracks(string $query, int $page = 1, int $perPage = 25): array
    {
        return $this->authenticatedRequest('GET', '/catalog/search/', [
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
    public function getTopTracks(int $page = 1, int $perPage = 25): array
    {
        return $this->authenticatedRequest('GET', '/catalog/tracks/', [
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
    private function authenticatedRequest(string $method, string $endpoint, array $query = []): array
    {
        return $this->request($method, $endpoint, $query, [
            'Authorization' => 'Bearer ' . $this->accessToken,
        ]);
    }
}
