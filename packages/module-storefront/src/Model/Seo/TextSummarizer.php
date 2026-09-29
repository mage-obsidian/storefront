<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Model\Seo;

class TextSummarizer
{
    public const int DEFAULT_LENGTH = 160;

    public function summarize(string $html, int $maxLength = self::DEFAULT_LENGTH): string
    {
        $withoutDirectives = preg_replace('/\{\{[^}]*}}/', ' ', $html) ?? $html;
        $decoded = html_entity_decode($withoutDirectives, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $withoutBlocks = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $decoded) ?? $decoded;
        $text = strip_tags($withoutBlocks);
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($text === '' || $maxLength <= 0 || mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $cut = mb_substr($text, 0, $maxLength);
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > 0) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut, " \t\n\r\0\x0B.,;:-") . '…';
    }
}
