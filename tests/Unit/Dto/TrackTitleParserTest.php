<?php

declare(strict_types=1);

namespace Beatport\Tests\Unit\Dto;

use Beatport\Dto\TrackTitleParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TrackTitleParserTest extends TestCase
{
    #[DataProvider('mixParsingProvider')]
    public function testParsesMixFromTitle(string $input, string $expectedTitle, ?string $expectedMix): void
    {
        [$title, $mix] = TrackTitleParser::parse($input);

        $this->assertSame($expectedTitle, $title);
        $this->assertSame($expectedMix, $mix);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: ?string}>
     */
    public static function mixParsingProvider(): array
    {
        return [
            'original mix in parentheses' => [
                'Track Name (Original Mix)',
                'Track Name',
                null,
            ],
            'extended mix in parentheses' => [
                'Track Name (Extended Mix)',
                'Track Name',
                'Extended Mix',
            ],
            'radio edit in parentheses' => [
                'Track Name (Radio Edit)',
                'Track Name',
                'Radio Edit',
            ],
            'club mix in brackets' => [
                'Track Name [Club Mix]',
                'Track Name',
                'Club Mix',
            ],
            'remix in parentheses' => [
                'Track Name (Artist Remix)',
                'Track Name',
                'Artist Remix',
            ],
            'dub mix' => [
                'Track Name (Dub Mix)',
                'Track Name',
                'Dub Mix',
            ],
            'instrumental' => [
                'Track Name (Instrumental)',
                'Track Name',
                'Instrumental',
            ],
            'acapella' => [
                'Track Name (Acapella)',
                'Track Name',
                'Acapella',
            ],
            'remastered version' => [
                'Track Name (Remastered Version)',
                'Track Name',
                'Remastered Version',
            ],
            'bootleg' => [
                'Track Name (Artist Bootleg)',
                'Track Name',
                'Artist Bootleg',
            ],
            'rework' => [
                'Track Name (2024 Rework)',
                'Track Name',
                '2024 Rework',
            ],
            'no mix - plain title' => [
                'Track Name',
                'Track Name',
                null,
            ],
            'no mix - feat in parentheses' => [
                'Track Name (feat. Artist)',
                'Track Name (feat. Artist)',
                null,
            ],
            'no mix - year in parentheses' => [
                'Track Name (2024)',
                'Track Name (2024)',
                null,
            ],
            'mix with extra spaces' => [
                'Track Name  (Original Mix) ',
                'Track Name',
                null,
            ],
            'lowercase mix' => [
                'Track Name (original mix)',
                'Track Name',
                null,
            ],
            'VIP mix' => [
                'Track Name (VIP Mix)',
                'Track Name',
                'VIP Mix',
            ],
        ];
    }

    public function testCleanAlbumName(): void
    {
        $this->assertSame('Album Name', TrackTitleParser::cleanAlbumName('Album Name EP'));
        $this->assertSame('Album Name', TrackTitleParser::cleanAlbumName('Album Name - EP'));
        $this->assertSame('Album Name', TrackTitleParser::cleanAlbumName('Album Name'));
        $this->assertNull(TrackTitleParser::cleanAlbumName(null));
        $this->assertNull(TrackTitleParser::cleanAlbumName(''));
    }
}
