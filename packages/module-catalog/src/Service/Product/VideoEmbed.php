<?php
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Catalog\Service\Product;

class VideoEmbed
{
    public const string YOUTUBE_PLAYER_HOST = 'www.youtube-nocookie.com';
    public const string VIMEO_PLAYER_HOST = 'player.vimeo.com';

    private const array YOUTUBE_HOSTS = [
        'youtube.com',
        'www.youtube.com',
        'm.youtube.com',
        'youtu.be',
        'youtube-nocookie.com',
        'www.youtube-nocookie.com',
    ];
    private const array VIMEO_HOSTS = ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'];
    private const string YOUTUBE_ID = '/^[A-Za-z0-9_-]{11}$/';

    public function resolve(string $address): ?array
    {
        $parts = parse_url(trim($address));
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        if (in_array($host, self::YOUTUBE_HOSTS, true)) {
            return $this->youtube($host, (string)($parts['path'] ?? ''), (string)($parts['query'] ?? ''));
        }
        if (in_array($host, self::VIMEO_HOSTS, true)) {
            return $this->vimeo((string)($parts['path'] ?? ''));
        }

        return null;
    }

    private function youtube(string $host, string $path, string $query): ?array
    {
        $identifier = '';
        if ($host === 'youtu.be') {
            $identifier = trim($path, '/');
        } elseif (preg_match('#^/(?:embed|shorts|live)/([^/?]+)#', $path, $match) === 1) {
            $identifier = $match[1];
        } elseif ($path === '/watch') {
            parse_str($query, $arguments);
            $identifier = is_string($arguments['v'] ?? null) ? $arguments['v'] : '';
        }

        if (preg_match(self::YOUTUBE_ID, $identifier) !== 1) {
            return null;
        }

        return [
            'provider' => 'youtube',
            'embed' => 'https://' . self::YOUTUBE_PLAYER_HOST . '/embed/' . $identifier . '?autoplay=1&rel=0',
            'watch' => 'https://www.youtube.com/watch?v=' . $identifier,
        ];
    }

    private function vimeo(string $path): ?array
    {
        if (preg_match('#^/(?:video/)?(\d+)(?:/([a-f0-9]+))?/?$#', $path, $match) !== 1) {
            return null;
        }

        $hash = $match[2] ?? '';

        return [
            'provider' => 'vimeo',
            'embed' => 'https://' . self::VIMEO_PLAYER_HOST . '/video/' . $match[1] . '?autoplay=1'
                . ($hash === '' ? '' : '&h=' . $hash),
            'watch' => 'https://vimeo.com/' . $match[1] . ($hash === '' ? '' : '/' . $hash),
        ];
    }
}
