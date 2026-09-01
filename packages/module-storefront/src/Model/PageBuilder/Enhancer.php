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
use MageObsidian\Storefront\Model\PageBuilder\Detector\DetectorInterface;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use Throwable;

/**
 * Loads the behaviour Page Builder content needs, and only where it needs it.
 *
 * Most of what a merchant can save is finished markup; a handful of content
 * types are inert without script — a tab set shows every panel at once, a slider
 * is a column of stacked slides. So the behaviour is pulled in by the content
 * that asks for it rather than by every page.
 *
 * Which content asks for what is not written here. It arrives as a collection of
 * detectors declared in `di.xml`, so a module or a theme adds its own without
 * editing this class, and a page that matches none of them downloads nothing.
 */
class Enhancer
{
    /**
     * @param ViteResolver $viteResolver
     * @param SecureHtmlRenderer $secureRenderer
     * @param DetectorInterface[] $detectors
     */
    public function __construct(
        private readonly ViteResolver $viteResolver,
        private readonly SecureHtmlRenderer $secureRenderer,
        private readonly array $detectors = []
    ) {
    }

    /**
     * @param string $html
     *
     * @return string
     */
    public function enhance(string $html): string
    {
        $behaviours = '';
        foreach ($this->modulesFor($html) as $module) {
            try {
                $url = $this->viteResolver->getViteFileUrl($module);
            } catch (Throwable) {
                // A build that has not run is not a reason to lose the content.
                continue;
            }

            $behaviours .= $this->secureRenderer->renderTag(
                'script',
                ['type' => 'module', 'src' => $url],
                '',
                false
            );
        }

        return $html . $behaviours;
    }

    /**
     * @param string $html
     *
     * @return string[] Each behaviour once, in the order the map declares them.
     */
    private function modulesFor(string $html): array
    {
        $modules = [];
        foreach ($this->detectors as $detector) {
            if ($detector instanceof DetectorInterface && $detector->matches($html)) {
                $modules[$detector->getModule()] = true;
            }
        }

        return array_keys($modules);
    }
}
