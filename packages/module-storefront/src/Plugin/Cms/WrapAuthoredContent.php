<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Plugin\Cms;

use Magento\Cms\Block\Block as CmsBlock;
use Magento\Cms\Block\Page as CmsPage;

/**
 * Wraps merchant-authored CMS output in a class the theme can style.
 *
 * A Tailwind preflight removes the browser defaults for `h2`, `ul`, `blockquote`
 * and the rest, which is right for the designed storefront and wrong for content
 * an admin pasted as plain HTML: it renders as an undifferentiated wall of text.
 * The theme restores typography for `.cms-content`, and this puts every page and
 * block inside it so nobody has to remember a class.
 *
 * Neither `Cms\Block\Page` nor `Cms\Block\Block` renders through a template — both
 * return the filtered content straight from `_toHtml()` — so there is nothing to
 * override in the theme and the wrapper has to be added here.
 */
class WrapAuthoredContent
{
    public const string CONTENT_CLASS = 'cms-content';

    /**
     * @param CmsPage|CmsBlock $subject
     * @param string $result
     *
     * @return string
     */
    public function afterToHtml(CmsPage|CmsBlock $subject, $result): string
    {
        $html = (string)$result;

        return trim($html) === ''
            ? $html
            : sprintf('<div class="%s">%s</div>', self::CONTENT_CLASS, $html);
    }
}
