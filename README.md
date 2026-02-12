# Beatport API Client

[![CI](https://github.com/kemo/beatport-api-client/actions/workflows/ci.yml/badge.svg)](https://github.com/kemo/beatport-api-client/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/kemo/beatport-api-client/graph/badge.svg)](https://codecov.io/gh/kemo/beatport-api-client)
[![PHPStan level 6](https://img.shields.io/badge/PHPStan-level%206-brightgreen)](https://phpstan.org/)
[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-blue)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

A standalone PHP client for the Beatport API. Provides three complementary clients for different access patterns: OAuth API, internal API, and web scraping.

## Features

- **OAuth API client** — Authenticated access to Beatport API v4 (library, tracks, search)
- **Internal API client** — Public access to tracks, artists, labels, releases, genres, and charts
- **Scrape client** — Session-based web scraping for library and track data
- **Full auth flow** — OAuth PKCE authorization and username/password login
- **DTOs** — Structured `BeatportTrack` and `LoginResult` objects
- **Domain exceptions** — `AuthenticationException`, `InvalidSessionException`, `SessionExpiredException`

## Installation

```bash
composer require kemo/beatport-api-client
```

Requires PHP 8.4+ and a PSR-18 compatible HTTP client. The package uses `symfony/http-client-contracts`, so any Symfony HTTP client implementation works:

```bash
composer require symfony/http-client
```

## Usage

### Internal API Client (No Auth Required)

The `InternalApiClient` accesses Beatport's internal API without authentication:

```php
use Kemo\Beatport\InternalApiClient;
use Symfony\Component\HttpClient\HttpClient;

$client = new InternalApiClient(HttpClient::create());

// Tracks
$track = $client->getTrack(123456);
$tracks = $client->getTracks(page: 1, perPage: 25, orderBy: '-publish_date');
$topTracks = $client->getTopTracks(genreId: 1, page: 1, perPage: 100);

// Artists
$artist = $client->getArtist(12345);
$artistTracks = $client->getArtistTopTracks(artistId: 12345, perPage: 10);

// Labels
$label = $client->getLabel(678);
$labelTracks = $client->getLabelTopTracks(labelId: 678, perPage: 10);

// Releases
$release = $client->getRelease(789);

// Genres
$genres = $client->getGenres();
$genre = $client->getGenre(1);
$genreTracks = $client->getGenreTopTracks(genreId: 1, perPage: 10);

// Charts
$chart = $client->getChart(456);
$charts = $client->getCharts(page: 1, perPage: 25);

// Search
$results = $client->search(query: 'Daft Punk', type: 'tracks', page: 1, perPage: 25);
```

### OAuth API Client

The `ApiClient` requires an OAuth access token:

```php
use Kemo\Beatport\ApiClient;
use Symfony\Component\HttpClient\HttpClient;

$client = new ApiClient(HttpClient::create(), accessToken: 'your-access-token');

$account = $client->getAccount();
$myTracks = $client->getMyBeatportTracks(perPage: 100);
$track = $client->getTrack(123456);
$results = $client->searchTracks(query: 'Bicep', page: 1, perPage: 25);
$topTracks = $client->getTopTracks(page: 1, perPage: 25);
```

### Authentication

#### OAuth PKCE Flow

```php
use Kemo\Beatport\AuthClient;
use Symfony\Component\HttpClient\HttpClient;

$auth = new AuthClient(HttpClient::create());

// Step 1: Get authorization URL
$url = $auth->getAuthorizationUrl(
    clientId: 'your-client-id',
    redirectUri: 'https://your-app.com/callback',
    codeChallenge: $challenge,
);

// Step 2: Exchange code for tokens
$tokens = $auth->exchangeCodeForTokens(
    clientId: 'your-client-id',
    redirectUri: 'https://your-app.com/callback',
    code: $authorizationCode,
    codeVerifier: $verifier,
);
// $tokens = ['access_token' => '...', 'refresh_token' => '...', 'expires_in' => 3600]

// Step 3: Refresh when expired
$refreshed = $auth->refreshAccessToken(
    clientId: 'your-client-id',
    refreshToken: $tokens['refresh_token'],
);
```

#### Username/Password Login

```php
use Kemo\Beatport\LoginClient;
use Kemo\Beatport\AuthClient;
use Symfony\Component\HttpClient\HttpClient;

$httpClient = HttpClient::create();
$login = new LoginClient($httpClient, new AuthClient($httpClient));

$result = $login->login(username: 'user@example.com', password: 'password');
// LoginResult with: sessionCookies, username, accessToken, refreshToken, expiresIn, beatportUserId
```

### Scrape Client

The `ScrapeClient` uses session cookies for web scraping:

```php
use Kemo\Beatport\ScrapeClient;
use Symfony\Component\HttpClient\HttpClient;

$scraper = new ScrapeClient(HttpClient::create(), requestDelayUs: 500_000);
$scraper->setSession($sessionCookies);

if ($scraper->isSessionValid()) {
    $tracks = $scraper->getLibraryTracks(page: 1);
    $allTracks = $scraper->getAllLibraryTracks(maxPages: 50);
    $pageCount = $scraper->getLibraryPageCount(maxPages: 50);
    $track = $scraper->getTrack(trackId: 123456, slug: 'track-name');
    $results = $scraper->searchTracks(query: 'Disclosure', page: 1);
}
```

### DTOs

#### BeatportTrack

```php
use Kemo\Beatport\Dto\BeatportTrack;

// From API response
$track = BeatportTrack::fromApiResponse($apiData);

// From scraped data
$track = BeatportTrack::fromScrapedData($scrapedData);

$track->beatportId;  // string
$track->title;       // string
$track->mix;         // ?string
$track->artistName;  // ?string
$track->albumName;   // ?string
$track->durationMs;  // ?int
$track->bpm;         // ?int
$track->musicalKey;  // ?string
$track->genre;       // ?string
$track->label;       // ?string
```

### Exception Handling

```php
use Kemo\Beatport\Exception\AuthenticationException;
use Kemo\Beatport\Exception\InvalidSessionException;
use Kemo\Beatport\Exception\SessionExpiredException;

try {
    $login->login('user@example.com', 'wrong-password');
} catch (AuthenticationException $e) {
    // Authentication failed
}

try {
    $scraper->setSession('invalid-cookies');
} catch (InvalidSessionException $e) {
    // Invalid session cookies
}

try {
    $scraper->getLibraryTracks();
} catch (SessionExpiredException $e) {
    // Session has expired
}
```

## Architecture

```
src/
├── AbstractApiClient.php      # Shared HTTP client base class
├── ApiClient.php              # OAuth API v4 client
├── InternalApiClient.php      # Internal API client (no auth)
├── ScrapeClient.php           # Web scraping client
├── AuthClient.php             # OAuth PKCE flow
├── LoginClient.php            # Username/password login
├── Beatport.php               # URL constants
├── Dto/
│   ├── BeatportTrack.php      # Track data transfer object
│   ├── LoginResult.php        # Login result DTO
│   └── TrackTitleParser.php   # Title/mix parser utility
├── Exception/
│   ├── AuthenticationException.php
│   ├── InvalidSessionException.php
│   └── SessionExpiredException.php
└── Util/
    └── SlugHelper.php         # URL slug generation
```

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run tests with coverage
composer test:coverage

# Static analysis
composer phpstan

# Check code style
composer cs:check

# Fix code style
composer cs:fix

# Full QA pipeline (cs:check + phpstan + test)
composer qa
```

## License

MIT. See [LICENSE](LICENSE).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
