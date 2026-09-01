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
 * Re-emits the stylesheets Page Builder saves inside its content so the platform
 * can hash them.
 *
 * Page Builder stores what an author styled as `<style>` elements written
 * straight into the content row. Under a policy that disallows inline styles —
 * the one this project ships — the browser drops them and the page renders as
 * unstyled markup. The rules are the author's, not the theme's, so they cannot
 * move into the build; what they need is the hash the platform adds to anything
 * rendered through its own renderer.
 *
 * There are two of them, not one, and they look nothing alike. What the author
 * styled is scoped as `#html-body [data-pb-style="…"]`. What the author uploaded
 * as a background arrives as a generated class instead, and it is the only sheet
 * Page Builder writes outside its own scoping convention — which is exactly why
 * recognising a single marker used to lose it.
 *
 * Recognising it is necessary and not sufficient. Page Builder builds that class
 * with `uniqid()`, so the sheet is a different string on every render and the
 * hash the platform computed never describes the sheet the browser receives.
 * Measured on the storefront: six style blocks on the page, five hashes in the
 * header, and the background one blocked. So the class is replaced by one
 * derived from the rules it carries before anything is handed over — in the
 * sheet and on the element that wears it, or the rules would stop finding it.
 *
 * Only Page Builder's own blocks are touched. A `<style>` an admin typed into a
 * plain HTML block is left exactly where it was: hashing that would be a policy
 * decision this has no business making.
 */
class StyleBlock
{
    /**
     * The literal markers that tell a sheet Page Builder generated apart from
     * one somebody pasted: the scope it writes the author's rules against, and
     * the class prefix it generates for a background image.
     */
    private const array MARKERS = ['[data-pb-style', '.background-image-'];

    private const string PATTERN = '#<style\b[^>]*>(.*?)</style>#is';

    /**
     * The class Page Builder generates per background, as `uniqid()` writes it:
     * the prefix plus at least the thirteen characters it produces by default.
     */
    private const string GENERATED_CLASS = '/background-image-[0-9a-f]{13,}/';

    private const string CLASS_PREFIX = 'background-image-';

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
        if (stripos($html, '<style') === false || !$this->isGenerated($html)) {
            return $html;
        }

        $html = $this->stabiliseGeneratedClasses($html);

        return (string)preg_replace_callback(
            self::PATTERN,
            function (array $match): string {
                $css = $match[1];

                return $this->isGenerated($css)
                    ? $this->secureRenderer->renderTag('style', ['type' => 'text/css'], $css, false)
                    : $match[0];
            },
            $html
        );
    }

    /**
     * @param string $html
     *
     * @return string
     */
    private function stabiliseGeneratedClasses(string $html): string
    {
        if (!preg_match_all(self::PATTERN, $html, $blocks, PREG_SET_ORDER)) {
            return $html;
        }

        $stable = [];
        foreach ($blocks as $block) {
            $css = $block[1];
            if (!preg_match_all(self::GENERATED_CLASS, $css, $found)) {
                continue;
            }

            foreach (array_unique($found[0]) as $generated) {
                if (isset($stable[$generated])) {
                    continue;
                }

                $stable[$generated] = self::CLASS_PREFIX . substr(
                    hash('sha256', str_replace($generated, '', $css)),
                    0,
                    13
                );
            }
        }

        return $stable === [] ? $html : strtr($html, $stable);
    }

    /**
     * @param string $css
     *
     * @return bool
     */
    private function isGenerated(string $css): bool
    {
        foreach (self::MARKERS as $marker) {
            if (str_contains($css, $marker)) {
                return true;
            }
        }

        return false;
    }
}
