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
 * Checkout heading.
 *
 * MageObsidian suppresses the frontend layout of non-opted-in core modules, so
 * the native checkout page never renders its <h1>. This block restores it. Unlike
 * the cart page (whose core controller sets the document <title>), the checkout
 * controller does not — so the <title> is set in our checkout_index_index.xml
 * head instead, and this block only owns the visible heading.
 */
class CheckoutTitle extends Template
{
    /**
     * The checkout heading (the page <h1>).
     *
     * @return Phrase
     */
    public function getHeading(): Phrase
    {
        return __('Checkout');
    }
}
