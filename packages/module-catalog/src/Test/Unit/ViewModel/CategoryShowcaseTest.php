<?php
declare(strict_types=1);

namespace MageObsidian\Catalog\Test\Unit\ViewModel;

use Magento\Catalog\Model\Category;
use Magento\Framework\Registry;
use MageObsidian\Catalog\ViewModel\CategoryShowcase;
use PHPUnit\Framework\TestCase;

/**
 * Showcase routing for the framework demo. We assert the contract the dispatcher
 * template relies on: the What's New / Sale slots map to their template ids by
 * url_key, every other category (and the no-category case) returns null so normal
 * listings render no showcase. Needs Magento Catalog types (see phpunit.ci.xml).
 */
class CategoryShowcaseTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Category::class)) {
            $this->markTestSkipped('Magento Catalog is not available in this runtime.');
        }
    }

    private function viewModel(mixed $currentCategory): CategoryShowcase
    {
        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->with('current_category')->willReturn($currentCategory);

        return new CategoryShowcase($registry);
    }

    public function testNoCurrentCategoryYieldsNull(): void
    {
        $this->assertNull($this->viewModel(null)->getShowcaseId());
    }

    public function testWhatsNewUrlKeyMapsToWhatsNewShowcase(): void
    {
        $this->assertSame('whats-new', $this->viewModel($this->category('what-is-new'))->getShowcaseId());
    }

    public function testSaleUrlKeyMapsToCommunityShowcase(): void
    {
        $this->assertSame('community', $this->viewModel($this->category('sale'))->getShowcaseId());
    }

    public function testUnmappedUrlKeyYieldsNull(): void
    {
        $this->assertNull($this->viewModel($this->category('women'))->getShowcaseId());
    }

    private function category(string $urlKey): Category
    {
        $category = $this->createMock(Category::class);
        $category->method('getUrlKey')->willReturn($urlKey);

        return $category;
    }
}
