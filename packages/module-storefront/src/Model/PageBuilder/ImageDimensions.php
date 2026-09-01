<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Model\PageBuilder;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Throwable;

class ImageDimensions
{
    private const string PATTERN = '#<img\b[^>]*\bdata-element="(?:desktop|mobile)_image"[^>]*>#i';

    private const string MEDIA_SEGMENT = '/media/';

    /** @var array<string, array{0: int, 1: int}|null> */
    private array $measured = [];

    public function __construct(
        private readonly Filesystem $filesystem
    ) {
    }

    public function inject(string $html): string
    {
        if (stripos($html, '<img') === false || !str_contains($html, 'data-element="')) {
            return $html;
        }

        return (string)preg_replace_callback(
            self::PATTERN,
            fn (array $match): string => $this->withDimensions($match[0]),
            $html
        );
    }

    private function withDimensions(string $tag): string
    {
        if (preg_match('/\swidth\s*=/i', $tag) === 1 || preg_match('/\sheight\s*=/i', $tag) === 1) {
            return $tag;
        }

        if (preg_match('/\ssrc="([^"]+)"/i', $tag, $found) !== 1) {
            return $tag;
        }

        $size = $this->sizeOf($found[1]);
        if ($size === null) {
            return $tag;
        }

        return substr($tag, 0, -1)
            . sprintf(' width="%d" height="%d"', $size[0], $size[1])
            . substr($tag, -1);
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function sizeOf(string $src): ?array
    {
        $relative = $this->relativePathOf($src);
        if ($relative === null) {
            return null;
        }

        if (array_key_exists($relative, $this->measured)) {
            return $this->measured[$relative];
        }

        $this->measured[$relative] = $this->read($relative);

        return $this->measured[$relative];
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function read(string $relative): ?array
    {
        try {
            $media = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
            if (!$media->isExist($relative)) {
                return null;
            }

            $size = getimagesize($media->getAbsolutePath($relative));
        } catch (Throwable) {
            return null;
        }

        if (!is_array($size) || (int)($size[0] ?? 0) <= 0 || (int)($size[1] ?? 0) <= 0) {
            return null;
        }

        return [(int)$size[0], (int)$size[1]];
    }

    private function relativePathOf(string $src): ?string
    {
        $path = parse_url(html_entity_decode($src), PHP_URL_PATH);
        if (!is_string($path)) {
            return null;
        }

        $at = strpos($path, self::MEDIA_SEGMENT);
        if ($at === false) {
            return null;
        }

        $relative = ltrim(substr($path, $at + strlen(self::MEDIA_SEGMENT)), '/');

        return $relative === '' || str_contains($relative, '..') ? null : $relative;
    }
}
