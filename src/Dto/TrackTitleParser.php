<?php

declare(strict_types=1);

namespace Kemo\Beatport\Dto;

final class TrackTitleParser
{
    /**
     * Parse a track title and extract the mix if present.
     *
     * Examples:
     * - "Track Name (Original Mix)" -> ["Track Name", null] (Original Mix is not stored)
     * - "Track Name (Original)" -> ["Track Name", null] (treated as Original Mix)
     * - "Track Name (Extended Mix)" -> ["Track Name", "Extended Mix"]
     * - "Track Name [Radio Edit]" -> ["Track Name", "Radio Edit"]
     * - "Track Name" -> ["Track Name", null]
     * - "Track Name (feat. Artist)" -> ["Track Name (feat. Artist)", null] (not a mix)
     *
     * @return array{0: string, 1: ?string} [title, mix]
     */
    public static function parse(string $fullTitle): array
    {
        // First, check for "(Original)" which should be treated as Original Mix
        $originalPattern = '/\s*[\(\[](original)\s*[\)\]]\s*$/i';
        if (preg_match($originalPattern, $fullTitle)) {
            $title = trim(preg_replace($originalPattern, '', $fullTitle));

            return [$title, null]; // Don't store Original Mix
        }

        // Common mix patterns at the end of track titles
        // Matches: (Original Mix), (Extended Mix), (Radio Edit), [Club Mix], etc.
        $mixPattern = '/\s*[\(\[]([^)\]]*(?:mix|edit|remix|dub|version|rework|bootleg|remaster|remastered|cut|instrumental|acapella|a capella)(?:\s+\d+)?)\s*[\)\]]\s*$/i';

        if (preg_match($mixPattern, $fullTitle, $matches)) {
            $mix = trim($matches[1]);
            $title = trim(preg_replace($mixPattern, '', $fullTitle));

            // Don't store "Original Mix" - treat it as the default
            if (preg_match('/^original\s*mix$/i', $mix)) {
                return [$title, null];
            }

            return [$title, $mix];
        }

        return [$fullTitle, null];
    }

    /**
     * Clean album name by removing common suffixes.
     *
     * Examples:
     * - "Album Name EP" -> "Album Name"
     * - "Album Name - EP" -> "Album Name"
     * - "Album Name" -> "Album Name"
     */
    public static function cleanAlbumName(?string $albumName): ?string
    {
        if ($albumName === null || $albumName === '') {
            return null;
        }

        // Remove trailing " EP" or " - EP"
        $cleaned = preg_replace('/\s*-?\s*EP$/i', '', $albumName);

        return trim($cleaned) ?: null;
    }
}
