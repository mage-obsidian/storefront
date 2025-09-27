<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Block\Catalog;

use Magento\Catalog\Model\Category;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;
use MageObsidian\Storefront\Block\Catalog\CategoryTitle;
use PHPUnit\Framework\TestCase;

/**
 * The category heading block. We assert the data path (current category name);
 * setting the page-config title is a layout-time side effect exercised in the
 * browser. Needs Magento framework types, so it runs in a Magento root.
 */
class CategoryTitleTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Context::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testReturnsCurrentCategoryName(): void
    {
        $category = $this->createMock(Category::class);
        $category->method('getName')->willReturn('Bags');

        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->with('current_category')->willReturn($category);

        $block = new CategoryTitle($this->createMock(Context::class), $registry);

        $this->assertSame('Bags', $block->getCategoryName());
    }

    public function testReturnsEmptyStringWithoutCurrentCategory(): void
    {
        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->willReturn(null);

        $block = new CategoryTitle($this->createMock(Context::class), $registry);

        $this->assertSame('', $block->getCategoryName());
    }
}
