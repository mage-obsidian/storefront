<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Block;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template\Context;
use MageObsidian\ModernFrontend\Block\Template;
use MageObsidian\ModernFrontend\ViewModel\Image;
use MageObsidian\ModernFrontend\ViewModel\SchemaOrg;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use MageObsidian\Storefront\Model\Navigation\MenuTree;
use Throwable;

/**
 * Host block for a navigation island, carrying the cache tags of the categories
 * it renders.
 *
 * The identities are what make a layout `ttl` safe here: under Varnish,
 * LayoutPlugin drops an ESI block's tags from the page assuming the fragment
 * carries its own, and Esi::execute() only sets them for an IdentityInterface
 * block. A menu that tags nothing is a menu no category save can purge.
 */
class Navigation extends Template implements IdentityInterface
{
    /**
     * @param Context $context
     * @param ViteResolver $viteResolver
     * @param SchemaOrg $schemaOrg
     * @param Image $image
     * @param MenuTree $menuTree
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        ViteResolver $viteResolver,
        SchemaOrg $schemaOrg,
        Image $image,
        private readonly MenuTree $menuTree,
        array $data = []
    ) {
        parent::__construct($context, $viteResolver, $schemaOrg, $image, $data);
    }

    /**
     * Levels of menu categories this island renders, from the layout argument.
     *
     * @return int
     */
    public function getMaxDepth(): int
    {
        return max(1, (int)$this->getData('max_depth'));
    }

    /**
     * @inheritDoc
     */
    public function getIdentities(): array
    {
        try {
            return $this->menuTree->getIdentities($this->getMaxDepth());
        } catch (Throwable) {
            // The ViewModel falls back to a demo menu on the same failure; a demo
            // menu has no categories to tag.
            return [];
        }
    }
}
