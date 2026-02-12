<?php

declare(strict_types=1);

namespace Kemo\Beatport\Tests\Unit;

use Kemo\Beatport\AuthClient;
use Kemo\Beatport\Dto\LoginResult;
use Kemo\Beatport\LoginClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class LoginClientTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private AuthClient&MockObject $authClient;
    private LoginClient $loginClient;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->authClient = $this->createMock(AuthClient::class);
        $this->loginClient = new LoginClient($this->httpClient, $this->authClient);
    }

    public function testLoginReturnsLoginResultWithSessionCookies(): void
    {
        $loginResponse = $this->createMock(ResponseInterface::class);
        $loginResponse->method('getHeaders')->willReturn([
            'set-cookie' => ['session_id=abc123; Path=/'],
        ]);
        $loginResponse->method('getContent')->willReturn(json_encode([
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]));

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($loginResponse);

        // No client ID scraped - OAuth skipped
        $this->authClient->method('scrapeClientId')->willReturn(null);

        $result = $this->loginClient->login('testuser', 'password123');

        $this->assertInstanceOf(LoginResult::class, $result);
        $this->assertSame('session_id=abc123', $result->sessionCookies);
        $this->assertSame('testuser', $result->username);
        $this->assertNull($result->accessToken);
    }

    public function testLoginThrowsOnInvalidCredentials(): void
    {
        $loginResponse = $this->createMock(ResponseInterface::class);
        $loginResponse->method('getHeaders')->willReturn(['set-cookie' => []]);
        $loginResponse->method('getContent')->willReturn(json_encode([
            'detail' => 'Invalid credentials',
        ]));

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($loginResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid credentials');

        $this->loginClient->login('baduser', 'badpassword');
    }

    public function testLoginThrowsOnNoCookies(): void
    {
        $loginResponse = $this->createMock(ResponseInterface::class);
        $loginResponse->method('getHeaders')->willReturn(['set-cookie' => []]);
        $loginResponse->method('getContent')->willReturn(json_encode([
            'username' => 'testuser',
        ]));

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($loginResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Login succeeded but no session cookies received');

        $this->loginClient->login('testuser', 'password');
    }

    public function testLoginContinuesWhenOAuthFails(): void
    {
        $loginResponse = $this->createMock(ResponseInterface::class);
        $loginResponse->method('getHeaders')->willReturn([
            'set-cookie' => ['session_id=valid; Path=/'],
        ]);
        $loginResponse->method('getContent')->willReturn(json_encode([
            'username' => 'testuser',
        ]));

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($loginResponse);

        $this->authClient->method('scrapeClientId')
            ->willThrowException(new RuntimeException('Scrape failed'));

        $result = $this->loginClient->login('testuser', 'password');

        $this->assertSame('session_id=valid', $result->sessionCookies);
        $this->assertNull($result->accessToken);
    }

    public function testLoginThrowsOnStringErrorResponse(): void
    {
        $loginResponse = $this->createMock(ResponseInterface::class);
        $loginResponse->method('getHeaders')->willReturn(['set-cookie' => []]);
        $loginResponse->method('getContent')->willReturn(json_encode('Account locked'));

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($loginResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Account locked');

        $this->loginClient->login('testuser', 'password');
    }
}
