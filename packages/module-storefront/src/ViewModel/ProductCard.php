<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Throwable;

/**
 * Presentation logic for the reusable product card, consumed from Twig as
 * `block.getCard().isQuickAdd(product)`. The card itself reads image, price and
 * add-to-cart params straight off the ListProduct block (no reason to re-wrap
 * core); this ViewModel only owns the one decision worth testing: whether a
 * product can be added to the cart straight from a listing, or must go to the
 * PDP to choose options.
 */
class ProductCard implements ArgumentInterface
{
    /**
     * Whether the product can be added to the cart directly from a listing.
     *
     * Quick-add requires the product to be saleable and to need no configuration:
     * simple/virtual with no required options add in one click, while anything
     * configurable (configurable/bundle/grouped, or a simple product carrying
     * required custom options) must route to the PDP to choose options.
     *
     * Note: we deliberately do NOT use isPossibleBuyFromList() — Configurable
     * overrides it to always return true ("handled by add to cart action"), which
     * would wrongly render a quick-add form whose POST has no super_attribute and
     * just bounces to the PDP with an error. canConfigure() is the correct signal:
     * true for composite types and options-bearing products, false for plain
     * simple/virtual.
     *
     * @param ProductInterface $product
     * @return bool
     */
    public function isQuickAdd(ProductInterface $product): bool
    {
        try {
            return $product->isSaleable()
                && !$product->getTypeInstance()->canConfigure($product);
        } catch (Throwable) {
            return false;
        }
    }
}
