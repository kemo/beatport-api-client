<?php

declare(strict_types=1);

namespace Kemo\Beatport\Tests\Unit;

use Kemo\Beatport\AuthClient;
use Kemo\Beatport\Exception\AuthenticationException;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class AuthClientTest extends TestCase
{
    public function testGetAuthorizationUrlContainsRequiredParams(): void
    {
        $authClient = new AuthClient($this->createStub(HttpClientInterface::class));

        $url = $authClient->getAuthorizationUrl('test_client_id', 'https://example.com/callback', 'test_challenge');

        $this->assertStringContainsString('client_id=test_client_id', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('redirect_uri=', $url);
        $this->assertStringContainsString('code_challenge=test_challenge', $url);
        $this->assertStringContainsString('code_challenge_method=S256', $url);
    }

    public function testExchangeCodeForTokensSuccess(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'access_token' => 'bp_access_token',
            'refresh_token' => 'bp_refresh_token',
            'expires_in' => 3600,
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $result = (new AuthClient($httpClient))->exchangeCodeForTokens('client_id', 'https://example.com/callback', 'auth_code', 'code_verifier');

        $this->assertSame('bp_access_token', $result['access_token']);
        $this->assertSame('bp_refresh_token', $result['refresh_token']);
        $this->assertSame(3600, $result['expires_in']);
    }

    public function testExchangeCodeThrowsOnInvalidResponse(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['error' => 'invalid_grant']);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid token response from Beatport');

        (new AuthClient($httpClient))->exchangeCodeForTokens('client_id', 'https://example.com/callback', 'bad_code', 'verifier');
    }

    public function testRefreshAccessTokenSuccess(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'access_token' => 'new_token',
            'expires_in' => 3600,
            'refresh_token' => 'new_refresh',
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $result = (new AuthClient($httpClient))->refreshAccessToken('client_id', 'old_refresh');

        $this->assertSame('new_token', $result['access_token']);
        $this->assertSame('new_refresh', $result['refresh_token']);
    }

    public function testRefreshAccessTokenWithoutNewRefreshToken(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'access_token' => 'new_token',
            'expires_in' => 3600,
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $result = (new AuthClient($httpClient))->refreshAccessToken('client_id', 'old_refresh');

        $this->assertSame('new_token', $result['access_token']);
        $this->assertArrayNotHasKey('refresh_token', $result);
    }

    public function testRefreshAccessTokenThrowsOnFailure(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['error' => 'invalid']);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Failed to refresh Beatport token');

        (new AuthClient($httpClient))->refreshAccessToken('client_id', 'bad_refresh');
    }

    public function testScrapeClientIdFindsId(): void
    {
        $docsResponse = $this->createStub(ResponseInterface::class);
        $docsResponse->method('getContent')->willReturn(
            '<html><script src="/static/btprt/app.123.js"></script></html>',
        );

        $jsResponse = $this->createStub(ResponseInterface::class);
        $jsResponse->method('getContent')->willReturn(
            "const config = { API_CLIENT_ID: 'scraped_client_id_123' };",
        );

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($docsResponse, $jsResponse);

        $this->assertSame('scraped_client_id_123', (new AuthClient($httpClient))->scrapeClientId());
    }

    public function testScrapeClientIdReturnsNullWhenNotFound(): void
    {
        $docsResponse = $this->createStub(ResponseInterface::class);
        $docsResponse->method('getContent')->willReturn('<html>No JS files</html>');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->willReturn($docsResponse);

        $this->assertNull((new AuthClient($httpClient))->scrapeClientId());
    }
}
