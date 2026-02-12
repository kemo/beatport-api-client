<?php

declare(strict_types=1);

namespace Kemo\Beatport\Dto;

final readonly class BeatportTrack
{
    public function __construct(
        public string $beatportId,
        public string $title,
        public ?string $mix,
        public ?string $artistName,
        public ?string $albumName,
        public ?int $durationMs,
        public ?int $bpm,
        public ?string $musicalKey,
        public ?string $genre,
        public ?string $label,
    ) {
    }

    /**
     * Create from Beatport API track object.
     *
     * @param array<string, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        $artistNames = array_map(
            fn ($a) => $a['name'] ?? '',
            $data['artists'] ?? [],
        );

        $albumName = $data['release']['name'] ?? null;

        $rawTitle = $data['name'] ?? '';
        $mixName = $data['mix_name'] ?? null;

        if ($mixName !== null && $mixName !== '' && strtolower($mixName) !== 'original mix' && strtolower($mixName) !== 'original') {
            $title = $rawTitle;
            $mix = $mixName;
        } else {
            [$title, $mix] = TrackTitleParser::parse($rawTitle);
            if ($mixName !== null && (strtolower($mixName) === 'original mix' || strtolower($mixName) === 'original')) {
                $mix = null;
            }
        }

        $durationMs = isset($data['length_ms']) ? (int) $data['length_ms'] : null;
        if ($durationMs === null && isset($data['length'])) {
            $parts = explode(':', $data['length']);
            if (count($parts) === 2) {
                $durationMs = ((int) $parts[0] * 60 + (int) $parts[1]) * 1000;
            }
        }

        $bpm = isset($data['bpm']) ? (int) $data['bpm'] : null;

        $key = null;
        if (isset($data['key']['name'])) {
            $key = $data['key']['name'];
        } elseif (isset($data['key'])) {
            $key = is_string($data['key']) ? $data['key'] : null;
        }

        $genre = $data['genre']['name'] ?? null;
        $label = $data['release']['label']['name'] ?? $data['label']['name'] ?? null;

        return new self(
            beatportId: (string) $data['id'],
            title: $title,
            mix: $mix,
            artistName: implode(', ', $artistNames) ?: null,
            albumName: TrackTitleParser::cleanAlbumName($albumName),
            durationMs: $durationMs,
            bpm: $bpm,
            musicalKey: $key,
            genre: $genre,
            label: $label,
        );
    }

    /**
     * Create from scraped HTML track data.
     *
     * @param array{
     *   id: int,
     *   title: string,
     *   artist: ?string,
     *   mix: ?string,
     *   bpm: ?int,
     *   key: ?string,
     *   genre: ?string,
     *   label: ?string,
     *   duration: ?string,
     *   slug: string,
     *   releaseDate?: ?string,
     *   previewUrl?: ?string
     * } $data
     */
    public static function fromScrapedData(array $data): self
    {
        [$title, $parsedMix] = TrackTitleParser::parse($data['title']);

        $mix = $data['mix'] ?? $parsedMix;

        if ($mix !== null && (strtolower($mix) === 'original mix' || strtolower($mix) === 'original')) {
            $mix = null;
        }

        $durationMs = null;
        if (isset($data['duration']) && $data['duration'] !== null) {
            $parts = explode(':', $data['duration']);
            if (count($parts) === 2) {
                $durationMs = ((int) $parts[0] * 60 + (int) $parts[1]) * 1000;
            }
        }

        return new self(
            beatportId: (string) $data['id'],
            title: $title,
            mix: $mix,
            artistName: $data['artist'] ?? null,
            albumName: null,
            durationMs: $durationMs,
            bpm: $data['bpm'] ?? null,
            musicalKey: $data['key'] ?? null,
            genre: $data['genre'] ?? null,
            label: $data['label'] ?? null,
        );
    }
}
