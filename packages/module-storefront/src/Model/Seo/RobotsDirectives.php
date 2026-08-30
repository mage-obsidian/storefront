<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Model\Seo;

use MageObsidian\Storefront\Model\Config\SeoConfig;

class RobotsDirectives
{
    public const string NOINDEX = 'NOINDEX';

    public function __construct(
        private readonly SeoConfig $config
    ) {
    }

    public function extend(string $robots): string
    {
        $current = preg_split('/[\s,]+/', trim($robots), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($current === []) {
            return '';
        }

        foreach ($current as $directive) {
            if (strtoupper($directive) === self::NOINDEX) {
                return '';
            }
        }

        $known = array_change_key_case(array_flip($current), CASE_LOWER);
        $added = [];
        foreach ($this->config->getRobotsDirectives() as $directive) {
            if (!isset($known[strtolower($directive)])) {
                $added[] = $directive;
                $known[strtolower($directive)] = true;
            }
        }

        if ($added === []) {
            return '';
        }

        return implode(',', array_merge($current, $added));
    }
}
