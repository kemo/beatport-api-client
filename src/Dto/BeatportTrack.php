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
        public ?string $bpm,
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
        // Extract artist names
        $artistNames = array_map(
            fn ($a) => $a['name'] ?? '',
            $data['artists'] ?? [],
        );

        // Get album/release name
        $albumName = $data['release']['name'] ?? null;

        // Parse title and mix
        $rawTitle = $data['name'] ?? '';
        $mixName = $data['mix_name'] ?? null;

        // If mix_name is provided by API, use it; otherwise parse from title
        if ($mixName !== null && $mixName !== '' && strtolower($mixName) !== 'original mix' && strtolower($mixName) !== 'original') {
            $title = $rawTitle;
            $mix = $mixName;
        } else {
            [$title, $mix] = TrackTitleParser::parse($rawTitle);
            // If API provides mix_name and it's "Original Mix", override to null
            if ($mixName !== null && (strtolower($mixName) === 'original mix' || strtolower($mixName) === 'original')) {
                $mix = null;
            }
        }

        // Convert duration from seconds to milliseconds
        $durationMs = isset($data['length_ms']) ? (int) $data['length_ms'] : null;
        if ($durationMs === null && isset($data['length'])) {
            // length is in "MM:SS" format
            $parts = explode(':', $data['length']);
            if (count($parts) === 2) {
                $durationMs = ((int) $parts[0] * 60 + (int) $parts[1]) * 1000;
            }
        }

        // Extract BPM
        $bpm = isset($data['bpm']) ? (string) $data['bpm'] : null;

        // Extract key
        $key = null;
        if (isset($data['key']['name'])) {
            $key = $data['key']['name'];
        } elseif (isset($data['key'])) {
            $key = is_string($data['key']) ? $data['key'] : null;
        }

        // Extract genre
        $genre = $data['genre']['name'] ?? null;

        // Extract label (from release.label.name)
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
        // Parse title and mix using TrackTitleParser
        [$title, $parsedMix] = TrackTitleParser::parse($data['title']);

        // Use explicit mix if provided, otherwise use parsed
        $mix = $data['mix'] ?? $parsedMix;

        // Normalize "Original Mix" to null
        if ($mix !== null && (strtolower($mix) === 'original mix' || strtolower($mix) === 'original')) {
            $mix = null;
        }

        // Convert duration string "MM:SS" to milliseconds
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
            albumName: null, // Not always available from scraping
            durationMs: $durationMs,
            bpm: isset($data['bpm']) ? (string) $data['bpm'] : null,
            musicalKey: $data['key'] ?? null,
            genre: $data['genre'] ?? null,
            label: $data['label'] ?? null,
        );
    }
}
