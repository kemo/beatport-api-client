<?php

declare(strict_types=1);

namespace Beatport\Tests\Unit;

use Beatport\AuthClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class AuthClientTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private AuthClient $authClient;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->authClient = new AuthClient($this->httpClient);
    }

    public function testGetAuthorizationUrlContainsRequiredParams(): void
    {
        $url = $this->authClient->getAuthorizationUrl('test_client_id', 'https://example.com/callback', 'test_challenge');

        $this->assertStringContainsString('client_id=test_client_id', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('redirect_uri=', $url);
        $this->assertStringContainsString('code_challenge=test_challenge', $url);
        $this->assertStringContainsString('code_challenge_method=S256', $url);
    }

    public function testExchangeCodeForTokensSuccess(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'access_token' => 'bp_access_token',
            'refresh_token' => 'bp_refresh_token',
            'expires_in' => 3600,
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $result = $this->authClient->exchangeCodeForTokens('client_id', 'https://example.com/callback', 'auth_code', 'code_verifier');

        $this->assertSame('bp_access_token', $result['access_token']);
        $this->assertSame('bp_refresh_token', $result['refresh_token']);
        $this->assertSame(3600, $result['expires_in']);
    }

    public function testExchangeCodeThrowsOnInvalidResponse(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['error' => 'invalid_grant']);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid token response from Beatport');

        $this->authClient->exchangeCodeForTokens('client_id', 'https://example.com/callback', 'bad_code', 'verifier');
    }

    public function testRefreshAccessTokenSuccess(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'access_token' => 'new_token',
            'expires_in' => 3600,
            'refresh_token' => 'new_refresh',
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $result = $this->authClient->refreshAccessToken('client_id', 'old_refresh');

        $this->assertSame('new_token', $result['access_token']);
        $this->assertSame('new_refresh', $result['refresh_token']);
    }

    public function testRefreshAccessTokenWithoutNewRefreshToken(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'access_token' => 'new_token',
            'expires_in' => 3600,
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $result = $this->authClient->refreshAccessToken('client_id', 'old_refresh');

        $this->assertSame('new_token', $result['access_token']);
        $this->assertArrayNotHasKey('refresh_token', $result);
    }

    public function testRefreshAccessTokenThrowsOnFailure(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['error' => 'invalid']);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to refresh Beatport token');

        $this->authClient->refreshAccessToken('client_id', 'bad_refresh');
    }

    public function testScrapeClientIdFindsId(): void
    {
        $docsResponse = $this->createMock(ResponseInterface::class);
        $docsResponse->method('getContent')->willReturn(
            '<html><script src="/static/btprt/app.123.js"></script></html>',
        );

        $jsResponse = $this->createMock(ResponseInterface::class);
        $jsResponse->method('getContent')->willReturn(
            "const config = { API_CLIENT_ID: 'scraped_client_id_123' };",
        );

        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($docsResponse, $jsResponse);

        $clientId = $this->authClient->scrapeClientId();

        $this->assertSame('scraped_client_id_123', $clientId);
    }

    public function testScrapeClientIdReturnsNullWhenNotFound(): void
    {
        $docsResponse = $this->createMock(ResponseInterface::class);
        $docsResponse->method('getContent')->willReturn('<html>No JS files</html>');

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($docsResponse);

        $this->assertNull($this->authClient->scrapeClientId());
    }
}
