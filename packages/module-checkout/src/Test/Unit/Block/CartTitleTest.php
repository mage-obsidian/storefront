<?php
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\Block;

use Magento\Framework\View\Element\Template\Context;
use MageObsidian\Checkout\Block\CartTitle;
use PHPUnit\Framework\TestCase;

/**
 * The cart heading block. We assert the heading text (also used for the <h1>);
 * setting the page-config title is a layout-time side effect exercised in the
 * browser. Needs Magento framework types, so it runs in a Magento root.
 */
class CartTitleTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Context::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testHeadingIsTheShoppingBagLabel(): void
    {
        $block = new CartTitle($this->createMock(Context::class));

        $this->assertSame('Shopping Bag', $block->getHeading()->render());
    }
}
