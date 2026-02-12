<?php

declare(strict_types=1);

namespace Kemo\Beatport;

final class InternalApiClient extends AbstractApiClient
{
    protected function getBaseUrl(): string
    {
        return Beatport::INTERNAL_API_BASE_URL;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTrack(int $trackId): array
    {
        return $this->request('GET', '/catalog/tracks/' . $trackId . '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function getArtist(int $artistId): array
    {
        return $this->request('GET', '/catalog/artists/' . $artistId . '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function getArtistTopTracks(int $artistId, int $perPage = 10): array
    {
        return $this->request('GET', '/catalog/artists/' . $artistId . '/top-10-tracks/', [
            'per_page' => $perPage,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getLabel(int $labelId): array
    {
        return $this->request('GET', '/catalog/labels/' . $labelId . '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function getLabelTopTracks(int $labelId, int $perPage = 10): array
    {
        return $this->request('GET', '/catalog/labels/' . $labelId . '/top-10-tracks/', [
            'per_page' => $perPage,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getRelease(int $releaseId): array
    {
        return $this->request('GET', '/catalog/releases/' . $releaseId . '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function getGenres(): array
    {
        return $this->request('GET', '/catalog/genres/');
    }

    /**
     * @return array<string, mixed>
     */
    public function getGenre(int $genreId): array
    {
        return $this->request('GET', '/catalog/genres/' . $genreId . '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function getGenreTopTracks(int $genreId, int $perPage = 10): array
    {
        return $this->request('GET', '/catalog/genres/' . $genreId . '/top-10-tracks/', [
            'per_page' => $perPage,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function search(string $query, string $type = 'tracks', int $page = 1, int $perPage = 25): array
    {
        return $this->request('GET', '/catalog/search/', [
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
        return $this->request('GET', '/catalog/tracks/', [
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
        return $this->request('GET', '/catalog/tracks/top/' . $genreId . '/', [
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getChart(int $chartId): array
    {
        return $this->request('GET', '/catalog/charts/' . $chartId . '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function getCharts(int $page = 1, int $perPage = 25): array
    {
        return $this->request('GET', '/catalog/charts/', [
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }
}
