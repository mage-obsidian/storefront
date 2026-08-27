<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Model\PageBuilder;

use Magento\Framework\View\Helper\SecureHtmlRenderer;

/**
 * Re-emits the stylesheet Page Builder saves inside its content so the platform
 * can hash it.
 *
 * Page Builder stores what an author styled as a `<style>` element carrying
 * `#html-body [data-pb-style="…"]{…}` rules, written straight into the content
 * row. Under a policy that disallows inline styles — the one this project ships
 * — the browser drops that element and the page renders as unstyled markup. The
 * rules are the author's, not the theme's, so they cannot move into the build;
 * what they need is the hash the platform adds to anything rendered through its
 * own renderer.
 *
 * Only Page Builder's own block is touched. A `<style>` an admin typed into a
 * plain HTML block is left exactly where it was: hashing that would be a policy
 * decision this has no business making.
 */
class StyleBlock
{
    /**
     * The scope Page Builder generates every rule against, and the only marker
     * that tells its stylesheet apart from one somebody pasted.
     */
    private const string MARKER = '[data-pb-style';

    private const string PATTERN = '#<style\b[^>]*>(.*?)</style>#is';

    /**
     * @param SecureHtmlRenderer $secureRenderer
     */
    public function __construct(
        private readonly SecureHtmlRenderer $secureRenderer
    ) {
    }

    /**
     * @param string $html
     *
     * @return string
     */
    public function rewrite(string $html): string
    {
        if (!str_contains($html, self::MARKER) || stripos($html, '<style') === false) {
            return $html;
        }

        return (string)preg_replace_callback(
            self::PATTERN,
            function (array $match): string {
                $css = $match[1];

                return str_contains($css, self::MARKER)
                    ? $this->secureRenderer->renderTag('style', ['type' => 'text/css'], $css, false)
                    : $match[0];
            },
            $html
        );
    }
}
