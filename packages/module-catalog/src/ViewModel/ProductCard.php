<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Catalog\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Checkout\Helper\Cart as CartHelper;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Url\Helper\Data as UrlHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Throwable;

/**
 * Presentation logic for the reusable product card, consumed from Twig as
 * `block.getCard()`. The card is shared by the category listing AND the PDP
 * related/upsell lists, which run on different core blocks (ListProduct vs.
 * AbstractProduct subclasses) — and only ListProduct carries
 * getAddToCartPostParams. So the card sources BOTH the quick-add discriminator
 * and the add-to-cart POST params from this ViewModel, keeping it independent of
 * the host block class (calling getAddToCartPostParams on an upsell/related block
 * would otherwise hit the magic __call and return null).
 */
class ProductCard implements ArgumentInterface
{
    /**
     * @param CartHelper $cartHelper
     * @param UrlHelper $urlHelper
     */
    public function __construct(
        private readonly CartHelper $cartHelper,
        private readonly UrlHelper $urlHelper
    ) {
    }

    /**
     * Add-to-cart POST params (action URL + product/uenc data) for a quick-add
     * card, mirroring core's ListProduct helper so the shared card works on any
     * host block. Empty when the URL cannot be built.
     *
     * @param ProductInterface $product
     * @return array<string, mixed>
     */
    public function getAddToCartPostParams(ProductInterface $product): array
    {
        try {
            $url = $this->cartHelper->getAddUrl($product, ['_escape' => false]);

            return [
                'action' => $url,
                'data' => [
                    'product' => (int)$product->getId(),
                    ActionInterface::PARAM_NAME_URL_ENCODED => $this->urlHelper->getEncodedUrl($url),
                ],
            ];
        } catch (Throwable) {
            return [];
        }
    }

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
