<?php

declare(strict_types=1);

namespace Kemo\Beatport;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class InternalApiClient
{
    private const BASE_URL = 'https://api-internal.beatportprod.com/v4';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getTrack(int $trackId): array
    {
        return $this->request('/catalog/tracks/' . $trackId . '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function getArtist(int $artistId): array
    {
        return $this->request('/catalog/artists/' . $artistId . '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function getArtistTopTracks(int $artistId, int $perPage = 10): array
    {
        return $this->request('/catalog/artists/' . $artistId . '/top-10-tracks/', [
            'per_page' => $perPage,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getLabel(int $labelId): array
    {
        return $this->request('/catalog/labels/' . $labelId . '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function getLabelTopTracks(int $labelId, int $perPage = 10): array
    {
        return $this->request('/catalog/labels/' . $labelId . '/top-10-tracks/', [
            'per_page' => $perPage,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getRelease(int $releaseId): array
    {
        return $this->request('/catalog/releases/' . $releaseId . '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function getGenres(): array
    {
        return $this->request('/catalog/genres/');
    }

    /**
     * @return array<string, mixed>
     */
    public function getGenre(int $genreId): array
    {
        return $this->request('/catalog/genres/' . $genreId . '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function getGenreTopTracks(int $genreId, int $perPage = 10): array
    {
        return $this->request('/catalog/genres/' . $genreId . '/top-10-tracks/', [
            'per_page' => $perPage,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function search(string $query, string $type = 'tracks', int $page = 1, int $perPage = 25): array
    {
        return $this->request('/catalog/search/', [
            'q' => $query,
            'type' => $type,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getTracks(int $page = 1, int $perPage = 25, string $orderBy = '-publish_date'): array
    {
        return $this->request('/catalog/tracks/', [
            'page' => $page,
            'per_page' => $perPage,
            'order_by' => $orderBy,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getTopTracks(int $genreId, int $page = 1, int $perPage = 100): array
    {
        return $this->request('/catalog/tracks/top/' . $genreId . '/', [
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getChart(int $chartId): array
    {
        return $this->request('/catalog/charts/' . $chartId . '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function getCharts(int $page = 1, int $perPage = 25): array
    {
        return $this->request('/catalog/charts/', [
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    private function request(string $endpoint, array $query = []): array
    {
        $options = [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ];

        if (!empty($query)) {
            $options['query'] = $query;
        }

        $response = $this->httpClient->request('GET', self::BASE_URL . $endpoint, $options);

        return $response->toArray();
    }
}
