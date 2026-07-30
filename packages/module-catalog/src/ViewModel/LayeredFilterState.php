<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Catalog\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\LayeredNavigation\Block\Navigation;
use Magento\LayeredNavigation\Block\Navigation\State;
use Throwable;

/**
 * Filter summary for consumers that sit outside the layered-navigation sidebar:
 * the product-list toolbar owns the mobile "Filters" trigger, and the trigger
 * has to know whether there is anything to open and how many filters are applied.
 *
 * Reads the sidebar block the layout already generated rather than resolving the
 * layer again — rebuilding the filter list would re-run every option-count query
 * the sidebar just paid for.
 */
class LayeredFilterState implements ArgumentInterface
{
    public const string DEFAULT_BLOCK_NAME = 'catalog.leftnav';

    /** Ties the trigger's aria-controls to the dialog across two templates. */
    public const string DIALOG_ID = 'ln-filter-dialog';

    private const string STATE_ALIAS = 'state';

    /**
     * @param LayoutInterface $layout
     * @param string $blockName Name of the layered-navigation block to read.
     */
    public function __construct(
        private readonly LayoutInterface $layout,
        private readonly string $blockName = self::DEFAULT_BLOCK_NAME
    ) {
    }

    /**
     * Id the trigger points its aria-controls at.
     *
     * @return string
     */
    public function getDialogId(): string
    {
        return self::DIALOG_ID;
    }

    /**
     * Whether the sidebar renders at least one filter with options.
     *
     * @return bool
     */
    public function hasFilters(): bool
    {
        $navigation = $this->getNavigation();
        if ($navigation === null) {
            return false;
        }

        try {
            if (!$navigation->canShowBlock()) {
                return false;
            }

            foreach ($navigation->getFilters() as $filter) {
                if ($filter->getItemsCount() > 0) {
                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    /**
     * How many filters the visitor has applied.
     *
     * @return int
     */
    public function getActiveCount(): int
    {
        $navigation = $this->getNavigation();
        if ($navigation === null) {
            return 0;
        }

        try {
            $state = $navigation->getChildBlock(self::STATE_ALIAS);

            return $state instanceof State ? count($state->getActiveFilters()) : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * The layered-navigation block, when the layout generated one.
     *
     * @return Navigation|null
     */
    private function getNavigation(): ?Navigation
    {
        $block = $this->layout->getBlock($this->blockName);

        return $block instanceof Navigation ? $block : null;
    }
}
