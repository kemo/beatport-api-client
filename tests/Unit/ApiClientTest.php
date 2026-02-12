<?php

declare(strict_types=1);

namespace Kemo\Beatport\Tests\Unit;

use Kemo\Beatport\ApiClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ApiClientTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
    }

    private function createClient(string $token = 'test_token'): ApiClient
    {
        return new ApiClient($this->httpClient, $token);
    }

    private function createJsonResponse(array $data): ResponseInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn($data);

        return $response;
    }

    public function testGetMyBeatportTracksSinglePage(): void
    {
        $response = $this->createJsonResponse([
            'results' => [
                ['id' => 1, 'name' => 'Track 1'],
                ['id' => 2, 'name' => 'Track 2'],
            ],
            'next' => null,
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $result = $this->createClient()->getMyBeatportTracks();

        $this->assertCount(2, $result);
    }

    public function testGetMyBeatportTracksPagination(): void
    {
        $page1 = $this->createJsonResponse([
            'results' => [['id' => 1, 'name' => 'Track 1']],
            'next' => 'https://api.beatport.com/v4/my/beatport/tracks/?page=2',
        ]);

        $page2 = $this->createJsonResponse([
            'results' => [['id' => 2, 'name' => 'Track 2']],
            'next' => null,
        ]);

        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($page1, $page2);

        $result = $this->createClient()->getMyBeatportTracks();

        $this->assertCount(2, $result);
    }

    public function testGetTrack(): void
    {
        $trackData = ['id' => 123, 'name' => 'Test Track', 'bpm' => 128];

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://api.beatport.com/v4/catalog/tracks/123/', $this->anything())
            ->willReturn($this->createJsonResponse($trackData));

        $result = $this->createClient()->getTrack(123);

        $this->assertSame(123, $result['id']);
    }

    public function testGetAccount(): void
    {
        $accountData = ['id' => 'user123', 'username' => 'testuser'];

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://api.beatport.com/v4/my/account/', $this->anything())
            ->willReturn($this->createJsonResponse($accountData));

        $result = $this->createClient()->getAccount();

        $this->assertSame('user123', $result['id']);
    }

    public function testSearchTracks(): void
    {
        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($this->createJsonResponse([
                'results' => [['id' => 1, 'name' => 'Found Track']],
            ]));

        $result = $this->createClient()->searchTracks('test query');

        $this->assertArrayHasKey('results', $result);
    }

    public function testGetTopTracks(): void
    {
        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($this->createJsonResponse([
                'results' => [['id' => 1, 'name' => 'Top Track']],
            ]));

        $result = $this->createClient()->getTopTracks();

        $this->assertArrayHasKey('results', $result);
    }

    public function testSendsAuthorizationHeader(): void
    {
        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->anything(),
                $this->callback(function (array $options) {
                    return ($options['headers']['Authorization'] ?? '') === 'Bearer my_token';
                }),
            )
            ->willReturn($this->createJsonResponse(['id' => 'user']));

        $this->createClient('my_token')->getAccount();
    }
}
