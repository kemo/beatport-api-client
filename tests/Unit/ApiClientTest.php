<?php

declare(strict_types=1);

namespace Beatport\Tests\Unit;

use Beatport\ApiClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ApiClientTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private ApiClient $apiClient;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->apiClient = new ApiClient($this->httpClient);
    }

    private function createJsonResponse(array $data): ResponseInterface&MockObject
    {
        $response = $this->createMock(ResponseInterface::class);
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

        $result = $this->apiClient->getMyBeatportTracks('test_token');

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

        $result = $this->apiClient->getMyBeatportTracks('test_token');

        $this->assertCount(2, $result);
    }

    public function testGetTrack(): void
    {
        $trackData = ['id' => 123, 'name' => 'Test Track', 'bpm' => 128];

        $response = $this->createJsonResponse($trackData);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://api.beatport.com/v4/catalog/tracks/123/', $this->anything())
            ->willReturn($response);

        $result = $this->apiClient->getTrack('test_token', 123);

        $this->assertSame(123, $result['id']);
    }

    public function testGetAccount(): void
    {
        $accountData = ['id' => 'user123', 'username' => 'testuser'];

        $response = $this->createJsonResponse($accountData);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://api.beatport.com/v4/my/account/', $this->anything())
            ->willReturn($response);

        $result = $this->apiClient->getAccount('test_token');

        $this->assertSame('user123', $result['id']);
    }

    public function testSearchTracks(): void
    {
        $response = $this->createJsonResponse([
            'results' => [['id' => 1, 'name' => 'Found Track']],
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $result = $this->apiClient->searchTracks('test_token', 'test query');

        $this->assertArrayHasKey('results', $result);
    }

    public function testGetTopTracks(): void
    {
        $response = $this->createJsonResponse([
            'results' => [['id' => 1, 'name' => 'Top Track']],
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $result = $this->apiClient->getTopTracks('test_token');

        $this->assertArrayHasKey('results', $result);
    }

    public function testSendsAuthorizationHeader(): void
    {
        $response = $this->createJsonResponse(['id' => 'user']);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->anything(),
                $this->callback(function (array $options) {
                    return ($options['headers']['Authorization'] ?? '') === 'Bearer my_token';
                }),
            )
            ->willReturn($response);

        $this->apiClient->getAccount('my_token');
    }
}
