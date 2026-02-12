<?php

declare(strict_types=1);

namespace Kemo\Beatport\Tests\Unit\Dto;

use Kemo\Beatport\Dto\BeatportTrack;
use PHPUnit\Framework\TestCase;

final class BeatportTrackTest extends TestCase
{
    public function testFromApiResponseBasic(): void
    {
        $data = [
            'id' => 12345,
            'name' => 'Test Track',
            'artists' => [['name' => 'Test Artist']],
            'mix_name' => 'Extended Mix',
            'bpm' => 128,
            'length_ms' => 360000,
            'key' => ['name' => 'A Minor'],
            'genre' => ['name' => 'Tech House'],
            'release' => [
                'name' => 'Test EP',
                'label' => ['name' => 'Test Label'],
            ],
        ];

        $track = BeatportTrack::fromApiResponse($data);

        $this->assertSame('12345', $track->beatportId);
        $this->assertSame('Test Track', $track->title);
        $this->assertSame('Extended Mix', $track->mix);
        $this->assertSame('Test Artist', $track->artistName);
        $this->assertSame('Test', $track->albumName); // "Test EP" -> "Test" (EP stripped)
        $this->assertSame(360000, $track->durationMs);
        $this->assertSame('128', $track->bpm);
        $this->assertSame('A Minor', $track->musicalKey);
        $this->assertSame('Tech House', $track->genre);
        $this->assertSame('Test Label', $track->label);
    }

    public function testFromApiResponseOriginalMixIsNull(): void
    {
        $data = [
            'id' => 1,
            'name' => 'Track Name',
            'artists' => [],
            'mix_name' => 'Original Mix',
        ];

        $track = BeatportTrack::fromApiResponse($data);

        $this->assertSame('Track Name', $track->title);
        $this->assertNull($track->mix);
    }

    public function testFromApiResponseParsesLengthFromMmSs(): void
    {
        $data = [
            'id' => 1,
            'name' => 'Track',
            'artists' => [],
            'length' => '6:30',
        ];

        $track = BeatportTrack::fromApiResponse($data);

        $this->assertSame(390000, $track->durationMs);
    }

    public function testFromApiResponseMultipleArtists(): void
    {
        $data = [
            'id' => 1,
            'name' => 'Track',
            'artists' => [['name' => 'Artist A'], ['name' => 'Artist B']],
        ];

        $track = BeatportTrack::fromApiResponse($data);

        $this->assertSame('Artist A, Artist B', $track->artistName);
    }

    public function testFromScrapedData(): void
    {
        $data = [
            'id' => 999,
            'title' => 'Scraped Track (Extended Mix)',
            'artist' => 'Scraped Artist',
            'mix' => null,
            'bpm' => 130,
            'key' => 'B Minor',
            'genre' => 'House',
            'label' => 'Scrape Label',
            'duration' => '5:30',
            'slug' => 'scraped-track',
        ];

        $track = BeatportTrack::fromScrapedData($data);

        $this->assertSame('999', $track->beatportId);
        $this->assertSame('Scraped Track', $track->title);
        $this->assertSame('Extended Mix', $track->mix);
        $this->assertSame('Scraped Artist', $track->artistName);
        $this->assertSame(330000, $track->durationMs);
        $this->assertSame('130', $track->bpm);
        $this->assertSame('B Minor', $track->musicalKey);
    }

    public function testFromScrapedDataNormalizesOriginalMix(): void
    {
        $data = [
            'id' => 1,
            'title' => 'Track Name',
            'artist' => null,
            'mix' => 'Original Mix',
            'bpm' => null,
            'key' => null,
            'genre' => null,
            'label' => null,
            'duration' => null,
            'slug' => 'track-name',
        ];

        $track = BeatportTrack::fromScrapedData($data);

        $this->assertNull($track->mix);
    }
}
