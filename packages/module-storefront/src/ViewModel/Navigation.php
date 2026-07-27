<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use MageObsidian\Storefront\Model\Navigation\MenuTree;
use Throwable;

/**
 * Single source of the main navigation, consumed from Twig as
 * `block.getNavigation().getItems()` (registered as a layout `<argument>`). The
 * header, the mobile drawer and the footer all read it, so the nav lives in one
 * place.
 *
 * The tree — and its per-request memo — comes from MenuTree; what this adds is
 * the presentation contract: a demo list for a store with no menu categories (or
 * any failure), so the header still renders.
 *
 * @phpstan-import-type NavItem from MenuTree
 */
class Navigation implements ArgumentInterface
{
    /** @var array<int, array{label: string, url: string, active: bool}> */
    private const DEMO_ITEMS = [
        ['label' => 'New in', 'url' => '#', 'active' => false],
        ['label' => 'Outerwear', 'url' => '#', 'active' => false],
        ['label' => 'Tailoring', 'url' => '#', 'active' => false],
        ['label' => 'The Vitreous Edit', 'url' => '#', 'active' => false],
        ['label' => 'Archive', 'url' => '#', 'active' => false],
    ];

    /**
     * @param MenuTree $menuTree
     */
    public function __construct(private readonly MenuTree $menuTree)
    {
    }

    /**
     * Main navigation items, from the store's menu categories or a demo fallback.
     *
     * With $maxDepth > 1 each item may carry a nested `children` list (same shape,
     * recursive) so a theme can build a mega menu; the default keeps the previous
     * top-level-only output, so existing consumers (footer, mobile) are unchanged.
     *
     * @param int $maxDepth Levels of menu categories to load (1 = top level only).
     * @return array<int, NavItem>
     */
    public function getItems(int $maxDepth = 1): array
    {
        try {
            $items = $this->menuTree->get(max(1, $maxDepth));
        } catch (Throwable) {
            return self::DEMO_ITEMS;
        }

        return $items !== [] ? $items : self::DEMO_ITEMS;
    }
}
