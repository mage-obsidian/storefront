<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Catalog\Plugin\CatalogWidget;

use Magento\CatalogWidget\Block\Product\ProductsList;
use MageObsidian\Catalog\ViewModel\ProductCard;

/**
 * Renders the product-list widget with this theme's own card.
 *
 * A widget instance stores the template an administrator picked, so re-declaring
 * the widget would leave every widget already placed rendering a Luma grid this
 * theme has no styles for. The swap happens here instead, at render, and only
 * for the template Magento ships as its default: a merchant who chose a template
 * of their own keeps it.
 *
 * The card partial reads its image and price helpers off the block, which the
 * layout supplies on the category listing and cannot supply here — the widget
 * block is built by the widget system, not by a layout. So it is handed over
 * with the template.
 *
 * Page Builder's carousel appearance pins a template of its own, which expects
 * slick; it is swapped for the scroll-snapping strip this storefront enhances,
 * so the two appearances render the same card.
 */
class RenderProductListWidget
{
    public const string CORE_TEMPLATE = 'Magento_CatalogWidget::product/widget/content/grid.phtml';

    public const string OBSIDIAN_TEMPLATE = 'Magento_CatalogWidget::product/widget/content/grid.twig';

    public const string CORE_CAROUSEL_TEMPLATE = 'Magento_PageBuilder::catalog/product/widget/content/carousel.phtml';

    public const string OBSIDIAN_CAROUSEL_TEMPLATE = 'Magento_CatalogWidget::product/widget/content/carousel.twig';

    /**
     * @param ProductCard $card
     */
    public function __construct(
        private readonly ProductCard $card
    ) {
    }

    /**
     * @param ProductsList $subject
     *
     * @return void
     */
    public function beforeToHtml(ProductsList $subject): void
    {
        if ($subject->getData('card') === null) {
            $subject->setData('card', $this->card);
        }

        $template = (string)$subject->getTemplate();
        if ($template === self::CORE_TEMPLATE || $template === '') {
            $subject->setTemplate(self::OBSIDIAN_TEMPLATE);
            return;
        }
        if ($template === self::CORE_CAROUSEL_TEMPLATE) {
            $subject->setTemplate(self::OBSIDIAN_CAROUSEL_TEMPLATE);
        }
    }
}
