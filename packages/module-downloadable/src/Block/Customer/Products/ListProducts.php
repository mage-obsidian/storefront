<?php
/**
 * This file is part of the MageObsidian - Downloadable project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Downloadable\Block\Customer\Products;

use Magento\Downloadable\Block\Customer\Products\ListProducts as CoreListProducts;
use Magento\Theme\Block\Html\Pager;

/**
 * Purchased downloadable links with a pager the theme can render.
 *
 * Core builds the pager in _prepareLayout() with createBlock(), out of reach of a
 * layout <referenceBlock>, so it keeps Luma's pager.phtml. Since setCollection()
 * is also what page-sizes the item collection, leaving it unrendered capped the
 * list at one page with no way to reach the rest of the purchases.
 */
class ListProducts extends CoreListProducts
{
    public const string PAGER_TEMPLATE = 'Magento_Theme::html/pager.twig';

    /**
     * @inheritDoc
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName) Magento framework hook name.
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $pager = $this->getChildBlock('pager');
        if ($pager instanceof Pager) {
            $pager->setTemplate(self::PAGER_TEMPLATE);
        }

        return $this;
    }
}
