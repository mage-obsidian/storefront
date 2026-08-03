<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Search project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Search\Model\Fragment;

use Magento\Framework\View\LayoutInterface;
use MageObsidian\Search\Exception\FragmentUnavailableException;

/**
 * Renders the fragments off the page's own layout.
 *
 * The blocks are the ones the page would have rendered — same classes, same
 * templates, same third-party additions — so the fragment cannot drift from the
 * markup it replaces. Asking the layout for a block is what triggers the build,
 * which is also what puts the page-cache headers on the response.
 */
class Renderer
{
    public function __construct(private readonly SectionPool $sectionPool)
    {
    }

    /**
     * @return array<string, string> section key => rendered HTML
     * @throws FragmentUnavailableException
     */
    public function render(LayoutInterface $layout, string $actionName): array
    {
        $rendered = [];

        foreach ($this->sectionPool->get($actionName) as $section => $blockName) {
            $block = $layout->getBlock($blockName);
            if (!$block) {
                throw new FragmentUnavailableException(
                    __('Listing fragment section "%1" expects block "%2", which the layout did not produce.', $section, $blockName)
                );
            }
            $rendered[$section] = $block->toHtml();
        }

        return $rendered;
    }
}
