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
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use Throwable;

/**
 * Loads the behaviour Page Builder content needs, and only where it needs it.
 *
 * Two of the content types a merchant can save are inert without script: a tab
 * set shows every panel at once, and a slider is a column of stacked slides.
 * Everything else Page Builder renders is finished markup. So the module is
 * pulled in by the content that asks for it rather than by every page — a store
 * whose authors never used a tab set never pays for one.
 */
class Enhancer
{
    private const string ENHANCER = 'MageObsidian_Storefront::js/page-builder';

    /**
     * The content types whose behaviour lives in script.
     *
     * @var string[]
     */
    private const array INTERACTIVE = ['data-content-type="tabs"', 'data-content-type="slider"'];

    /**
     * @param ViteResolver $viteResolver
     * @param SecureHtmlRenderer $secureRenderer
     */
    public function __construct(
        private readonly ViteResolver $viteResolver,
        private readonly SecureHtmlRenderer $secureRenderer
    ) {
    }

    /**
     * @param string $html
     *
     * @return string
     */
    public function enhance(string $html): string
    {
        if (!$this->needsEnhancing($html)) {
            return $html;
        }

        try {
            $url = $this->viteResolver->getViteFileUrl(self::ENHANCER);
        } catch (Throwable) {
            // A build that has not run is not a reason to lose the content.
            return $html;
        }

        return $html . $this->secureRenderer->renderTag(
            'script',
            ['type' => 'module', 'src' => $url],
            '',
            false
        );
    }

    /**
     * @param string $html
     *
     * @return bool
     */
    private function needsEnhancing(string $html): bool
    {
        foreach (self::INTERACTIVE as $marker) {
            if (str_contains($html, $marker)) {
                return true;
            }
        }

        return false;
    }
}
