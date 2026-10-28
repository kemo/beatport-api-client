# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- `AuthClient::getAuthorizationUrl()` uses `/v4/auth/o/authorize/` with the trailing slash. The path without it returns 301.

### Changed

- README: document the client ID source, the accepted redirect URIs, the `library` scope caveat, and that the internal API host is private.

## [0.1.0] - 2025-01-01

### Added

- `ApiClient` for OAuth-authenticated Beatport API v4 access
- `InternalApiClient` for public internal API access (no auth required)
- `ScrapeClient` for web scraping with session-based authentication
- `AuthClient` for OAuth authorization code flow with PKCE
- `LoginClient` for username/password login with automatic token acquisition
- `BeatportTrack` DTO with factory methods for API and scraped data
- `LoginResult` DTO for login response data
- `TrackTitleParser` utility for parsing track titles and mix names
- `SlugHelper` utility for URL slug generation
- Domain exceptions: `AuthenticationException`, `InvalidSessionException`, `SessionExpiredException`
