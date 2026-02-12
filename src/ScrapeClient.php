<?php

declare(strict_types=1);

namespace Kemo\Beatport;

use Kemo\Beatport\Exception\SessionExpiredException;
use Kemo\Beatport\Util\SlugHelper;
use Exception;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ScrapeClient
{
    private ?string $sessionCookies = null;

    /**
     * @param int $requestDelayUs Delay between paginated requests in microseconds (default: 500ms)
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly int $requestDelayUs = 500_000,
    ) {
    }

    public function setSession(string $sessionCookies): void
    {
        $this->sessionCookies = $sessionCookies;
    }

    public function getSession(): ?string
    {
        return $this->sessionCookies;
    }

    public function isAuthenticated(): bool
    {
        return $this->sessionCookies !== null;
    }

    /**
     * Validate if the current session is still valid by checking library page.
     */
    public function isSessionValid(): bool
    {
        if ($this->sessionCookies === null) {
            return false;
        }

        try {
            $response = $this->request('GET', '/library/tracks', [
                'max_redirects' => 0,
            ]);

            return !$this->isSessionExpiredResponse($response);
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Get library tracks from the authenticated user's library.
     *
     * @return array<int, array{id: int, title: string, artist: ?string, mix: ?string, bpm: ?int, key: ?string, genre: ?string, label: ?string, duration: ?string, slug: string}>
     *
     * @throws SessionExpiredException
     */
    public function getLibraryTracks(int $page = 1): array
    {
        if (!$this->isAuthenticated()) {
            return [];
        }

        $response = $this->request('GET', '/library/tracks', [
            'query' => ['page' => $page],
            'max_redirects' => 0,
        ]);

        $this->validateAuthenticatedResponse($response);

        if ($response->getStatusCode() !== 200) {
            return [];
        }

        return $this->parseLibraryTrackList($response->getContent());
    }

    /**
     * Get all library tracks with pagination.
     *
     * @return array<int, array{id: int, title: string, artist: ?string, mix: ?string, bpm: ?int, key: ?string, genre: ?string, label: ?string, duration: ?string, slug: string}>
     *
     * @throws SessionExpiredException
     */
    public function getAllLibraryTracks(int $maxPages = 50): array
    {
        $allTracks = [];
        $page = 1;

        while ($page <= $maxPages) {
            $tracks = $this->getLibraryTracks($page);

            if (empty($tracks)) {
                break;
            }

            $allTracks = array_merge($allTracks, $tracks);
            ++$page;

            if ($this->requestDelayUs > 0) {
                usleep($this->requestDelayUs);
            }
        }

        return $allTracks;
    }

    /**
     * Get the total number of library pages by parsing pagination.
     *
     * @throws SessionExpiredException
     */
    public function getLibraryPageCount(int $maxPages = 50): int
    {
        if (!$this->isAuthenticated()) {
            return 0;
        }

        $response = $this->request('GET', '/library/tracks', [
            'query' => ['page' => 1],
            'max_redirects' => 0,
        ]);

        $this->validateAuthenticatedResponse($response);

        if ($response->getStatusCode() !== 200) {
            return 0;
        }

        $totalPages = $this->parsePaginationLastPage($response->getContent());

        return min($totalPages, $maxPages);
    }

    /**
     * Get track details by ID.
     *
     * @return array{id: int, title: string, artist: ?string, mix: ?string, bpm: ?int, key: ?string, genre: ?string, label: ?string, duration: ?string, releaseDate: ?string, slug: string, previewUrl: ?string}|null
     */
    public function getTrack(int $trackId, string $slug = 'track'): ?array
    {
        $response = $this->request('GET', "/track/{$slug}/{$trackId}");

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        return $this->parseTrackDetail($response->getContent(), $trackId);
    }

    /**
     * Search for tracks.
     *
     * @return array<int, array{id: int, title: string, artist: ?string, mix: ?string, bpm: ?int, key: ?string, genre: ?string, label: ?string, duration: ?string, slug: string}>
     */
    public function searchTracks(string $query, int $page = 1): array
    {
        $response = $this->request('GET', '/search', [
            'query' => [
                'q' => $query,
                'type' => 'tracks',
                'page' => $page,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            return [];
        }

        return $this->parseLibraryTrackList($response->getContent());
    }

    /**
     * @param array<string, mixed> $options
     */
    private function request(string $method, string $endpoint, array $options = []): ResponseInterface
    {
        $defaultHeaders = [
            'User-Agent' => Beatport::USER_AGENT,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'same-origin',
            'Sec-Fetch-User' => '?1',
        ];

        if ($this->sessionCookies !== null) {
            $defaultHeaders['Cookie'] = $this->sessionCookies;
        }

        $options['headers'] = array_merge($defaultHeaders, $options['headers'] ?? []);

        return $this->httpClient->request($method, Beatport::WEB_BASE_URL . $endpoint, $options);
    }

    private function isLoginPage(string $html): bool
    {
        return str_contains($html, 'id="login"')
            || str_contains($html, 'action="/account/login"')
            || str_contains($html, 'Sign In</button>')
            || (str_contains($html, 'login') && str_contains($html, 'password'));
    }

    private function isSessionExpiredResponse(ResponseInterface $response): bool
    {
        if ($response->getStatusCode() === 302) {
            $location = $response->getHeaders(false)['location'][0] ?? '';
            if (str_contains($location, 'login') || str_contains($location, 'account')) {
                return true;
            }
        }

        if ($response->getStatusCode() === 200) {
            return $this->isLoginPage($response->getContent(false));
        }

        return false;
    }

    /**
     * @throws SessionExpiredException
     */
    private function validateAuthenticatedResponse(ResponseInterface $response): void
    {
        if ($this->isSessionExpiredResponse($response)) {
            throw new SessionExpiredException();
        }
    }

    private function parsePaginationLastPage(string $html): int
    {
        $crawler = new Crawler($html);

        try {
            $paginationLinks = $crawler->filter('a[href*="page="], .pagination a');

            if ($paginationLinks->count() === 0) {
                return 1;
            }

            $lastPage = 1;
            $paginationLinks->each(function (Crawler $node) use (&$lastPage) {
                $href = $node->attr('href') ?? '';
                if (preg_match('/page=(\d+)/', $href, $matches)) {
                    $pageNum = (int) $matches[1];
                    if ($pageNum > $lastPage) {
                        $lastPage = $pageNum;
                    }
                }

                $text = trim($node->text());
                if (is_numeric($text)) {
                    $pageNum = (int) $text;
                    if ($pageNum > $lastPage) {
                        $lastPage = $pageNum;
                    }
                }
            });

            return $lastPage;
        } catch (Exception) {
            return 1;
        }
    }

    /**
     * @return array<int, array{id: int, title: string, artist: ?string, mix: ?string, bpm: ?int, key: ?string, genre: ?string, label: ?string, duration: ?string, slug: string}>
     */
    private function parseLibraryTrackList(string $html): array
    {
        $crawler = new Crawler($html);
        $tracks = [];

        $seenIds = [];
        $crawler->filter('a[href*="/track/"]')->each(function (Crawler $node) use (&$tracks, &$seenIds) {
            try {
                $href = $node->attr('href') ?? '';
                if (!preg_match('/\/track\/([^\/]+)\/(\d+)/', $href, $matches)) {
                    return;
                }

                $slug = $matches[1];
                $id = (int) $matches[2];

                if (isset($seenIds[$id])) {
                    return;
                }
                $seenIds[$id] = true;

                $title = trim($node->text());
                if ($title === '') {
                    return;
                }

                $parent = $node->ancestors()->first();
                $parentHtml = $parent->count() > 0 ? $parent->html() : '';

                $artist = $this->extractArtistFromContext($parent);

                $mix = null;
                if (preg_match('/\(([^)]+(?:Mix|Remix|Edit|Dub|Version))\)/i', $title, $mixMatch)) {
                    $mix = $mixMatch[1];
                }

                $bpm = null;
                if (preg_match('/(\d{2,3})\s*BPM/i', $parentHtml, $bpmMatch)) {
                    $bpm = (int) $bpmMatch[1];
                }

                $key = $this->extractKey($parentHtml);
                $duration = $this->extractDuration($parentHtml);
                $genre = $this->extractGenreFromContext($parent);
                $label = $this->extractLabelFromContext($parent);

                $tracks[] = [
                    'id' => $id,
                    'title' => $title,
                    'artist' => $artist,
                    'mix' => $mix,
                    'bpm' => $bpm,
                    'key' => $key,
                    'genre' => $genre,
                    'label' => $label,
                    'duration' => $duration,
                    'slug' => $slug,
                ];
            } catch (Exception) {
            }
        });

        return $tracks;
    }

    /**
     * @return array{id: int, title: string, artist: ?string, mix: ?string, bpm: ?int, key: ?string, genre: ?string, label: ?string, duration: ?string, releaseDate: ?string, slug: string, previewUrl: ?string}|null
     */
    private function parseTrackDetail(string $html, int $trackId): ?array
    {
        $crawler = new Crawler($html);

        try {
            $titleNode = $crawler->filter('h1')->first();
            if ($titleNode->count() === 0) {
                return null;
            }

            $title = trim($titleNode->text());

            $artists = [];
            $crawler->filter('a[href*="/artist/"]')->each(function (Crawler $node) use (&$artists) {
                $text = trim($node->text());
                if ($text !== '' && !in_array($text, $artists)) {
                    $artists[] = $text;
                }
            });
            $artist = !empty($artists) ? implode(', ', $artists) : null;

            $mix = null;
            $mixNode = $crawler->filter('.mix-name, [class*="mix"]')->first();
            if ($mixNode->count() > 0) {
                $mix = trim($mixNode->text());
            }
            if ($mix === null && preg_match('/\(([^)]+(?:Mix|Remix|Edit|Dub|Version))\)/i', $title, $mixMatch)) {
                $mix = $mixMatch[1];
            }

            $bpm = null;
            if (preg_match('/(\d{2,3})\s*BPM/i', $html, $bpmMatch)) {
                $bpm = (int) $bpmMatch[1];
            }
            if ($bpm === null) {
                $bpmNode = $crawler->filter('[class*="bpm"]')->first();
                if ($bpmNode->count() > 0 && preg_match('/(\d{2,3})/', $bpmNode->text(), $bpmMatch)) {
                    $bpm = (int) $bpmMatch[1];
                }
            }

            $key = $this->extractKey($html);
            $duration = $this->extractDuration($html);

            $genre = null;
            $genreNode = $crawler->filter('a[href*="/genre/"]')->first();
            if ($genreNode->count() > 0) {
                $genre = trim($genreNode->text());
            }

            $label = null;
            $labelNode = $crawler->filter('a[href*="/label/"]')->first();
            if ($labelNode->count() > 0) {
                $label = trim($labelNode->text());
            }

            $releaseDate = null;
            if (preg_match('/(\d{4}-\d{2}-\d{2})/', $html, $dateMatch)) {
                $releaseDate = $dateMatch[1];
            }

            $previewUrl = null;
            $previewNode = $crawler->filter('[data-preview-url], audio source, [src*="preview"]')->first();
            if ($previewNode->count() > 0) {
                $previewUrl = $previewNode->attr('data-preview-url') ?? $previewNode->attr('src');
            }

            return [
                'id' => $trackId,
                'title' => $title,
                'artist' => $artist,
                'mix' => $mix,
                'bpm' => $bpm,
                'key' => $key,
                'genre' => $genre,
                'label' => $label,
                'duration' => $duration,
                'releaseDate' => $releaseDate,
                'slug' => SlugHelper::slugify($title),
                'previewUrl' => $previewUrl,
            ];
        } catch (Exception) {
            return null;
        }
    }

    private function extractArtistFromContext(Crawler $parent): ?string
    {
        if ($parent->count() === 0) {
            return null;
        }

        $artists = [];
        $parent->filter('a[href*="/artist/"]')->each(function (Crawler $node) use (&$artists) {
            $text = trim($node->text());
            if ($text !== '' && !in_array($text, $artists)) {
                $artists[] = $text;
            }
        });

        return !empty($artists) ? implode(', ', $artists) : null;
    }

    private function extractGenreFromContext(Crawler $parent): ?string
    {
        if ($parent->count() === 0) {
            return null;
        }

        $genreNode = $parent->filter('a[href*="/genre/"]')->first();

        return $genreNode->count() > 0 ? trim($genreNode->text()) : null;
    }

    private function extractLabelFromContext(Crawler $parent): ?string
    {
        if ($parent->count() === 0) {
            return null;
        }

        $labelNode = $parent->filter('a[href*="/label/"]')->first();

        return $labelNode->count() > 0 ? trim($labelNode->text()) : null;
    }

    private function extractKey(string $html): ?string
    {
        $patterns = [
            '/([A-G][#b]?\s*(?:min|maj|minor|major))/i',
            '/\b(\d{1,2}[AB])\b/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $match)) {
                return trim($match[1]);
            }
        }

        return null;
    }

    private function extractDuration(string $html): ?string
    {
        if (preg_match('/\b(\d{1,2}:\d{2})\b/', $html, $match)) {
            return $match[1];
        }

        return null;
    }
}
