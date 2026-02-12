<?php

declare(strict_types=1);

namespace Kemo\Beatport\Tests\Unit;

use Kemo\Beatport\InternalApiClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class InternalApiClientTest extends TestCase
{
    private const BASE_URL = 'https://api-internal.beatportprod.com/v4';

    private HttpClientInterface&MockObject $httpClient;
    private InternalApiClient $client;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->client = new InternalApiClient($this->httpClient);
    }

    private function expectRequest(string $url, ?callable $optionsCheck = null): void
    {
        $args = ['GET', $url];

        if ($optionsCheck) {
            $args[] = $this->callback($optionsCheck);
        } else {
            $args[] = $this->anything();
        }

        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['id' => 1]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(...$args)
            ->willReturn($response);
    }

    public function testGetTrack(): void
    {
        $this->expectRequest(self::BASE_URL . '/catalog/tracks/123/');
        $result = $this->client->getTrack(123);
        $this->assertSame(1, $result['id']);
    }

    public function testGetArtist(): void
    {
        $this->expectRequest(self::BASE_URL . '/catalog/artists/456/');
        $this->client->getArtist(456);
    }

    public function testGetArtistTopTracks(): void
    {
        $this->expectRequest(self::BASE_URL . '/catalog/artists/456/top-10-tracks/', fn (array $opts) => ($opts['query']['per_page'] ?? null) === 10);
        $this->client->getArtistTopTracks(456);
    }

    public function testGetLabel(): void
    {
        $this->expectRequest(self::BASE_URL . '/catalog/labels/789/');
        $this->client->getLabel(789);
    }

    public function testGetLabelTopTracks(): void
    {
        $this->expectRequest(self::BASE_URL . '/catalog/labels/789/top-10-tracks/', fn (array $opts) => ($opts['query']['per_page'] ?? null) === 5);
        $this->client->getLabelTopTracks(789, 5);
    }

    public function testGetRelease(): void
    {
        $this->expectRequest(self::BASE_URL . '/catalog/releases/101/');
        $this->client->getRelease(101);
    }

    public function testGetGenres(): void
    {
        $this->expectRequest(self::BASE_URL . '/catalog/genres/');
        $this->client->getGenres();
    }

    public function testGetGenre(): void
    {
        $this->expectRequest(self::BASE_URL . '/catalog/genres/7/');
        $this->client->getGenre(7);
    }

    public function testGetGenreTopTracks(): void
    {
        $this->expectRequest(self::BASE_URL . '/catalog/genres/7/top-10-tracks/', fn (array $opts) => ($opts['query']['per_page'] ?? null) === 10);
        $this->client->getGenreTopTracks(7);
    }

    public function testSearch(): void
    {
        $this->expectRequest(self::BASE_URL . '/catalog/search/', function (array $opts) {
            return ($opts['query']['q'] ?? '') === 'deadmau5'
                && ($opts['query']['type'] ?? '') === 'tracks'
                && ($opts['query']['page'] ?? 0) === 1
                && ($opts['query']['per_page'] ?? 0) === 25;
        });
        $this->client->search('deadmau5');
    }

    public function testSearchWithType(): void
    {
        $this->expectRequest(self::BASE_URL . '/catalog/search/', fn (array $opts) => ($opts['query']['type'] ?? '') === 'artists');
        $this->client->search('test', 'artists');
    }

    public function testGetTracks(): void
    {
        $this->expectRequest(self::BASE_URL . '/catalog/tracks/', function (array $opts) {
            return ($opts['query']['page'] ?? 0) === 2
                && ($opts['query']['per_page'] ?? 0) === 50
                && ($opts['query']['order_by'] ?? '') === '-publish_date';
        });
        $this->client->getTracks(2, 50);
    }

    public function testGetTopTracks(): void
    {
        $this->expectRequest(self::BASE_URL . '/catalog/tracks/top/6/', function (array $opts) {
            return ($opts['query']['per_page'] ?? 0) === 100
                && ($opts['query']['page'] ?? 0) === 1;
        });
        $this->client->getTopTracks(6);
    }

    public function testGetChart(): void
    {
        $this->expectRequest(self::BASE_URL . '/catalog/charts/42/');
        $this->client->getChart(42);
    }

    public function testGetCharts(): void
    {
        $this->expectRequest(self::BASE_URL . '/catalog/charts/', fn (array $opts) => ($opts['query']['page'] ?? 0) === 1);
        $this->client->getCharts();
    }

    public function testDoesNotSendAuthorizationHeader(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->anything(),
                $this->callback(fn (array $opts) => !isset($opts['headers']['Authorization'])),
            )
            ->willReturn($response);

        $this->client->getGenres();
    }
}
