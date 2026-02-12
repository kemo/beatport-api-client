<?php

declare(strict_types=1);

namespace Kemo\Beatport;

use Kemo\Beatport\Exception\AuthenticationException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AuthClient
{
    private const AUTH_URL = Beatport::API_BASE_URL . '/auth/o/authorize';
    private const TOKEN_URL = Beatport::API_BASE_URL . '/auth/o/token/';
    private const DOCS_URL = Beatport::API_BASE_URL . '/docs/';
    private const SCOPES = ['library'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getAuthorizationUrl(string $clientId, string $redirectUri, string $codeChallenge): string
    {
        $params = [
            'client_id' => $clientId,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', self::SCOPES),
            'code_challenge_method' => 'S256',
            'code_challenge' => $codeChallenge,
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    public function exchangeCodeForTokens(string $clientId, string $redirectUri, string $code, string $codeVerifier): array
    {
        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'client_id' => $clientId,
                'code_verifier' => $codeVerifier,
            ],
        ]);

        $data = $response->toArray();

        if (!isset($data['access_token'], $data['refresh_token'])) {
            throw new AuthenticationException('Invalid token response from Beatport');
        }

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_in' => $data['expires_in'] ?? 3600,
        ];
    }

    /**
     * Refresh an access token.
     *
     * @return array{access_token: string, expires_in: int, refresh_token?: string}
     */
    public function refreshAccessToken(string $clientId, string $refreshToken): array
    {
        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $clientId,
            ],
        ]);

        $data = $response->toArray();

        if (!isset($data['access_token'])) {
            throw new AuthenticationException('Failed to refresh Beatport token');
        }

        $result = [
            'access_token' => $data['access_token'],
            'expires_in' => $data['expires_in'] ?? 3600,
        ];

        if (isset($data['refresh_token'])) {
            $result['refresh_token'] = $data['refresh_token'];
        }

        return $result;
    }

    /**
     * Scrape client_id from Beatport API docs JavaScript files.
     */
    public function scrapeClientId(): ?string
    {
        $response = $this->httpClient->request('GET', self::DOCS_URL, [
            'headers' => [
                'User-Agent' => Beatport::USER_AGENT,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ],
        ]);

        $html = $response->getContent();

        preg_match_all('/src="(\/static\/btprt\/[^"]+\.js)"/', $html, $matches);
        $jsUrls = $matches[1] ?? [];

        foreach ($jsUrls as $jsPath) {
            $jsUrl = 'https://api.beatport.com' . $jsPath;
            $jsResponse = $this->httpClient->request('GET', $jsUrl, [
                'headers' => [
                    'User-Agent' => Beatport::USER_AGENT,
                ],
            ]);

            $jsContent = $jsResponse->getContent();

            if (preg_match("/API_CLIENT_ID:\s*['\"]([^'\"]+)['\"]/", $jsContent, $clientMatch)) {
                return $clientMatch[1];
            }
        }

        return null;
    }
}
