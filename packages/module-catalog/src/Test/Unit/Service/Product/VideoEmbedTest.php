<?php
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Catalog\Test\Unit\Service\Product;

use MageObsidian\Catalog\Service\Product\VideoEmbed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VideoEmbedTest extends TestCase
{
    public static function youtubeAddresses(): array
    {
        return [
            'watch page' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'watch page with more parameters' => ['https://www.youtube.com/watch?feature=share&v=dQw4w9WgXcQ&t=10'],
            'mobile site' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ'],
            'short link' => ['https://youtu.be/dQw4w9WgXcQ'],
            'embed address' => ['https://www.youtube.com/embed/dQw4w9WgXcQ'],
            'privacy embed address' => ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
            'shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ'],
            'plain http' => ['http://www.youtube.com/watch?v=dQw4w9WgXcQ'],
        ];
    }

    #[DataProvider('youtubeAddresses')]
    public function testEveryYoutubeAddressBecomesThePrivacyPlayer(string $address): void
    {
        $this->assertSame([
            'provider' => 'youtube',
            'embed' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0',
            'watch' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ], (new VideoEmbed())->resolve($address));
    }

    public static function vimeoAddresses(): array
    {
        return [
            'page' => ['https://vimeo.com/76979871'],
            'www page' => ['https://www.vimeo.com/76979871'],
            'player address' => ['https://player.vimeo.com/video/76979871'],
        ];
    }

    #[DataProvider('vimeoAddresses')]
    public function testEveryVimeoAddressBecomesThePlayer(string $address): void
    {
        $this->assertSame([
            'provider' => 'vimeo',
            'embed' => 'https://player.vimeo.com/video/76979871?autoplay=1',
            'watch' => 'https://vimeo.com/76979871',
        ], (new VideoEmbed())->resolve($address));
    }

    public function testAnUnlistedVimeoVideoKeepsItsHash(): void
    {
        $this->assertSame(
            'https://player.vimeo.com/video/76979871?autoplay=1&h=abc123def4',
            (new VideoEmbed())->resolve('https://vimeo.com/76979871/abc123def4')['embed']
        );
    }

    public static function refused(): array
    {
        return [
            'another provider' => ['https://www.dailymotion.com/video/x7tgad0'],
            'a look-alike host' => ['https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ'],
            'a host that only ends alike' => ['https://notyoutube.com/watch?v=dQw4w9WgXcQ'],
            'no video id' => ['https://www.youtube.com/watch'],
            'an id that is not an id' => ['https://www.youtube.com/watch?v=<script>alert(1)</script>'],
            'a vimeo page without a number' => ['https://vimeo.com/channels/staffpicks'],
            'a script address' => ['javascript:alert(1)'],
            'an empty address' => [''],
            'a relative address' => ['/watch?v=dQw4w9WgXcQ'],
        ];
    }

    #[DataProvider('refused')]
    public function testAnythingElseIsRefused(string $address): void
    {
        $this->assertNull((new VideoEmbed())->resolve($address));
    }
}
