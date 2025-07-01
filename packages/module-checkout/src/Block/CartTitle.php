<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Checkout\Block;

use Magento\Framework\Phrase;
use Magento\Framework\View\Element\Template;

/**
 * Cart heading.
 *
 * MageObsidian suppresses the frontend layout of non-opted-in core modules, so
 * the native cart page <h1> never renders. This block restores it (branded
 * "Shopping Bag"). The document <title> is NOT set here: the core cart
 * controller (Magento\Checkout\Controller\Cart\Index) sets it to "Shopping Cart"
 * and runs after layout, so a block cannot override it without a controller
 * plugin — out of scope. A non-empty <title> is therefore still guaranteed.
 */
class CartTitle extends Template
{
    /**
     * The shopping bag heading (the page <h1>).
     *
     * @return Phrase
     */
    public function getHeading(): Phrase
    {
        return __('Shopping Bag');
    }
}
