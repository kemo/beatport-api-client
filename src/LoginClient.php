<?php

declare(strict_types=1);

namespace Kemo\Beatport;

use Kemo\Beatport\Dto\LoginResult;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class LoginClient
{
    private const API_BASE_URL = 'https://api.beatport.com/v4';
    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    private ?string $sessionCookies = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AuthClient $authClient,
    ) {
    }

    /**
     * Login to Beatport and obtain session cookies and optionally OAuth tokens.
     */
    public function login(string $username, string $password, ?string $clientId = null): LoginResult
    {
        // Step 1: Log into Beatport API to get session cookies
        $this->loginToWebsite($username, $password);

        if ($this->sessionCookies === null) {
            throw new RuntimeException('Failed to obtain session cookies');
        }

        $sessionCookies = $this->sessionCookies;

        // Step 2: Try to get OAuth tokens if clientId is provided or can be scraped
        $accessToken = null;
        $refreshToken = null;
        $expiresIn = null;
        $beatportUserId = null;

        try {
            $resolvedClientId = $clientId ?? $this->authClient->scrapeClientId();
            if ($resolvedClientId !== null) {
                $oauthResult = $this->obtainOAuthTokens($resolvedClientId);
                $accessToken = $oauthResult['access_token'];
                $refreshToken = $oauthResult['refresh_token'];
                $expiresIn = $oauthResult['expires_in'];

                // Try to get user profile
                try {
                    $profile = $this->fetchUserProfile($accessToken);
                    $userId = $profile['id'] ?? null;
                    $beatportUserId = $userId !== null ? (string) $userId : null;
                } catch (RuntimeException) {
                    // Profile fetch failed, continue without user ID
                }
            }
        } catch (RuntimeException) {
            // OAuth flow failed - continue with scrape-only credentials
        }

        return new LoginResult(
            sessionCookies: $sessionCookies,
            username: $username,
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            expiresIn: $expiresIn,
            beatportUserId: $beatportUserId,
        );
    }

    private function loginToWebsite(string $username, string $password): void
    {
        $response = $this->httpClient->request('POST', self::API_BASE_URL . '/auth/login/', [
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'username' => $username,
                'password' => $password,
            ],
        ]);

        $this->extractAndStoreCookies($response);

        $content = $response->getContent(false);
        $data = json_decode($content, true);

        if (is_string($data)) {
            throw new RuntimeException($data);
        }

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid response from Beatport API');
        }

        if (!is_array($data) || (!isset($data['username']) && !isset($data['email']))) {
            $errorMessage = is_array($data) ? ($data['detail'] ?? $data['error'] ?? 'Invalid credentials') : 'Invalid credentials';
            throw new RuntimeException($errorMessage);
        }

        if ($this->sessionCookies === null || empty($this->sessionCookies)) {
            throw new RuntimeException('Login succeeded but no session cookies received');
        }
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    private function obtainOAuthTokens(string $clientId): array
    {
        $authCode = $this->performOAuthAuthorization($clientId);
        if ($authCode === null) {
            throw new RuntimeException('Failed to obtain authorization code');
        }

        return $this->exchangeCodeForTokens($clientId, $authCode);
    }

    private function performOAuthAuthorization(string $clientId): ?string
    {
        $redirectUri = self::API_BASE_URL . '/auth/o/post-message/';

        $params = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
        ];

        $authUrl = self::API_BASE_URL . '/auth/o/authorize/?' . http_build_query($params);

        $response = $this->httpClient->request('GET', $authUrl, [
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Cookie' => $this->sessionCookies ?? '',
            ],
            'max_redirects' => 0,
        ]);

        if ($response->getStatusCode() === 302) {
            $location = $response->getHeaders(false)['location'][0] ?? '';

            if (preg_match('/[?&]code=([^&]+)/', $location, $matches)) {
                return urldecode($matches[1]);
            }

            $fullLocation = str_starts_with($location, 'http')
                ? $location
                : self::API_BASE_URL . $location;

            if (preg_match('/[?&]code=([^&]+)/', $fullLocation, $matches)) {
                return urldecode($matches[1]);
            }
        }

        if ($response->getStatusCode() === 200) {
            $content = $response->getContent(false);
            if (str_contains($content, 'invalid_request')) {
                throw new RuntimeException('OAuth authorization failed: invalid request');
            }
        }

        return null;
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    private function exchangeCodeForTokens(string $clientId, string $code): array
    {
        $redirectUri = self::API_BASE_URL . '/auth/o/post-message/';

        $tokenUrl = self::API_BASE_URL . '/auth/o/token/?' . http_build_query([
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
        ]);

        $response = $this->httpClient->request('POST', $tokenUrl, [
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/json',
                'Cookie' => $this->sessionCookies ?? '',
            ],
        ]);

        $data = $response->toArray();

        if (!isset($data['access_token'], $data['refresh_token'])) {
            throw new RuntimeException('Invalid token response from Beatport');
        }

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_in' => $data['expires_in'] ?? 3600,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchUserProfile(string $accessToken): array
    {
        $response = $this->httpClient->request('GET', self::API_BASE_URL . '/my/account/', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ],
        ]);

        return $response->toArray();
    }

    private function extractAndStoreCookies(ResponseInterface $response): void
    {
        $setCookieHeaders = $response->getHeaders(false)['set-cookie'] ?? [];
        $newCookies = $this->extractCookies($setCookieHeaders);

        if (empty($newCookies)) {
            return;
        }

        $existingCookies = [];
        if ($this->sessionCookies !== null) {
            foreach (explode('; ', $this->sessionCookies) as $cookie) {
                $parts = explode('=', $cookie, 2);
                if (count($parts) === 2) {
                    $existingCookies[$parts[0]] = $parts[1];
                }
            }
        }

        $existingCookies = array_merge($existingCookies, $newCookies);

        $cookieParts = [];
        foreach ($existingCookies as $name => $value) {
            $cookieParts[] = $name . '=' . $value;
        }
        $this->sessionCookies = implode('; ', $cookieParts);
    }

    /**
     * @return array<string, string>
     */
    private function extractCookies(array $setCookieHeaders): array
    {
        $cookies = [];
        foreach ($setCookieHeaders as $header) {
            if (preg_match('/^([^=]+)=([^;]*)/', $header, $matches)) {
                $cookies[$matches[1]] = $matches[2];
            }
        }

        return $cookies;
    }
}
