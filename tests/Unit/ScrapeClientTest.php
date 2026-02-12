<?php

declare(strict_types=1);

namespace Kemo\Beatport\Tests\Unit;

use Kemo\Beatport\Exception\SessionExpiredException;
use Kemo\Beatport\ScrapeClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ScrapeClientTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private ScrapeClient $client;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->client = new ScrapeClient($this->httpClient);
    }

    private function createResponse(int $status, string $content = '', array $headers = []): ResponseInterface&MockObject
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getContent')->willReturn($content);
        if (!empty($headers)) {
            $response->method('getHeaders')->willReturn($headers);
        }

        return $response;
    }

    public function testIsAuthenticatedReturnsFalseByDefault(): void
    {
        $this->assertFalse($this->client->isAuthenticated());
        $this->assertNull($this->client->getSession());
    }

    public function testSetSessionMakesAuthenticated(): void
    {
        $this->client->setSession('session_cookies_here');
        $this->assertTrue($this->client->isAuthenticated());
        $this->assertSame('session_cookies_here', $this->client->getSession());
    }

    public function testIsSessionValidReturnsFalseWhenNoSession(): void
    {
        $this->assertFalse($this->client->isSessionValid());
    }

    public function testIsSessionValidReturnsTrueOn200WithContent(): void
    {
        $response = $this->createResponse(200, '<html><body>Library content</body></html>');
        $response->method('getContent')->with(false)->willReturn('<html><body>Library content</body></html>');

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->client->setSession('valid_session');
        $this->assertTrue($this->client->isSessionValid());
    }

    public function testIsSessionValidReturnsFalseOn302ToLogin(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(302);
        $response->method('getHeaders')->willReturn([
            'location' => ['https://www.beatport.com/account/login'],
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->client->setSession('expired_session');
        $this->assertFalse($this->client->isSessionValid());
    }

    public function testIsSessionValidReturnsFalseOnLoginPage(): void
    {
        $loginHtml = '<html><body><form action="/account/login"><button>Sign In</button></form></body></html>';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn($loginHtml);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->client->setSession('expired_session');
        $this->assertFalse($this->client->isSessionValid());
    }

    public function testGetLibraryTracksReturnsEmptyWhenNotAuthenticated(): void
    {
        $this->assertSame([], $this->client->getLibraryTracks());
    }

    public function testGetLibraryTracksThrowsOnSessionExpired(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(302);
        $response->method('getHeaders')->willReturn([
            'location' => ['https://www.beatport.com/account/login'],
        ]);
        $response->method('getContent')->willReturn('');

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->client->setSession('expired_session');

        $this->expectException(SessionExpiredException::class);
        $this->client->getLibraryTracks();
    }

    public function testGetLibraryTracksParsesTracks(): void
    {
        $html = <<<HTML
        <html><body>
            <div>
                <a href="/track/test-track/12345">Test Track (Original Mix)</a>
                <a href="/artist/test-artist/678">Test Artist</a>
                <a href="/genre/techno/1">Techno</a>
                <a href="/label/test-label/99">Test Label</a>
                <span>128 BPM</span>
            </div>
        </body></html>
        HTML;

        $response = $this->createResponse(200, $html);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->client->setSession('valid_session');
        $tracks = $this->client->getLibraryTracks();

        $this->assertCount(1, $tracks);
        $this->assertSame(12345, $tracks[0]['id']);
        $this->assertSame('Test Track (Original Mix)', $tracks[0]['title']);
        $this->assertSame('test-track', $tracks[0]['slug']);
    }

    public function testGetTrackReturnsNullOnNon200(): void
    {
        $response = $this->createResponse(404);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $result = $this->client->getTrack(12345);
        $this->assertNull($result);
    }

    public function testGetTrackParsesDetailPage(): void
    {
        $html = <<<HTML
        <html><body>
            <h1>Test Track</h1>
            <a href="/artist/test-artist/1">Test Artist</a>
            <a href="/genre/house/5">House</a>
            <a href="/label/some-label/10">Some Label</a>
            <span>125 BPM</span>
            <span>5:30</span>
            <span>2024-01-15</span>
        </body></html>
        HTML;

        $response = $this->createResponse(200, $html);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $result = $this->client->getTrack(999, 'test-track');

        $this->assertNotNull($result);
        $this->assertSame(999, $result['id']);
        $this->assertSame('Test Track', $result['title']);
        $this->assertSame('Test Artist', $result['artist']);
    }

    public function testSearchTracks(): void
    {
        $html = <<<HTML
        <html><body>
            <a href="/track/found-track/555">Found Track</a>
        </body></html>
        HTML;

        $response = $this->createResponse(200, $html);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $tracks = $this->client->searchTracks('test query');

        $this->assertCount(1, $tracks);
        $this->assertSame(555, $tracks[0]['id']);
    }

    public function testSearchTracksReturnsEmptyOnNon200(): void
    {
        $response = $this->createResponse(500);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->assertSame([], $this->client->searchTracks('test'));
    }

    public function testGetLibraryPageCountReturnsZeroWhenNotAuthenticated(): void
    {
        $this->assertSame(0, $this->client->getLibraryPageCount());
    }

    public function testGetLibraryPageCountParsesPagination(): void
    {
        $html = <<<HTML
        <html><body>
            <a href="/library/tracks?page=1">1</a>
            <a href="/library/tracks?page=2">2</a>
            <a href="/library/tracks?page=5">5</a>
        </body></html>
        HTML;

        $response = $this->createResponse(200, $html);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->client->setSession('valid_session');
        $this->assertSame(5, $this->client->getLibraryPageCount());
    }

    public function testGetLibraryPageCountRespectsMaxPages(): void
    {
        $html = <<<HTML
        <html><body>
            <a href="/library/tracks?page=100">100</a>
        </body></html>
        HTML;

        $response = $this->createResponse(200, $html);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->client->setSession('valid_session');
        $this->assertSame(10, $this->client->getLibraryPageCount(10));
    }
}
