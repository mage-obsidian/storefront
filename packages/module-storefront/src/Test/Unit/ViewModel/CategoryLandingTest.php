<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Helper\Output as OutputHelper;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Registry;
use MageObsidian\Storefront\ViewModel\CategoryLanding;
use PHPUnit\Framework\TestCase;

/**
 * Landing tiles for static/PAGE categories. We assert the contract the template
 * relies on: no current category degrades to an empty list, visible children are
 * mapped to {label,url,count,image} tiles with a representative product image,
 * and the description is run through the catalog output filter. Needs Magento
 * Catalog types, so it runs in a Magento root (see phpunit.ci.xml).
 */
class CategoryLandingTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Category::class)) {
            $this->markTestSkipped('Magento Catalog is not available in this runtime.');
        }
    }

    private function viewModel(
        ?Category $currentCategory,
        ?CategoryCollectionFactory $childrenFactory = null,
        ?ProductCollectionFactory $productsFactory = null,
        ?ImageHelper $imageHelper = null,
        ?OutputHelper $outputHelper = null
    ): CategoryLanding {
        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->with('current_category')->willReturn($currentCategory);

        $visibility = $this->createMock(Visibility::class);
        $visibility->method('getVisibleInCatalogIds')->willReturn([3, 4]);

        return new CategoryLanding(
            $registry,
            $childrenFactory ?? $this->createMock(CategoryCollectionFactory::class),
            $productsFactory ?? $this->createMock(ProductCollectionFactory::class),
            $visibility,
            $imageHelper ?? $this->createMock(ImageHelper::class),
            $outputHelper ?? $this->createMock(OutputHelper::class)
        );
    }

    public function testNoCurrentCategoryYieldsNoTiles(): void
    {
        $this->assertSame([], $this->viewModel(null)->getItems());
    }

    public function testNoCurrentCategoryYieldsNoDescription(): void
    {
        $this->assertSame('', $this->viewModel(null)->getDescriptionHtml());
    }

    public function testChildrenAreMappedToTilesWithCountAndImage(): void
    {
        $parent = $this->createMock(Category::class);
        $parent->method('getId')->willReturn(20);

        $child = $this->createMock(Category::class);
        $child->method('getName')->willReturn('Tops');
        $child->method('getUrl')->willReturn('https://shop.test/women/tops.html');

        $childrenCollection = $this->createMock(CategoryCollection::class);
        $childrenCollection->method('addAttributeToSelect')->willReturnSelf();
        $childrenCollection->method('addAttributeToFilter')->willReturnSelf();
        $childrenCollection->method('setOrder')->willReturnSelf();
        $childrenCollection->method('getIterator')->willReturn(new \ArrayIterator([$child]));

        $childrenFactory = $this->createMock(CategoryCollectionFactory::class);
        $childrenFactory->method('create')->willReturn($childrenCollection);

        $firstProduct = $this->createMock(Product::class);
        $firstProduct->method('getId')->willReturn(101);

        $productsCollection = $this->createMock(ProductCollection::class);
        $productsCollection->method('addCategoryFilter')->willReturnSelf();
        $productsCollection->method('setVisibility')->willReturnSelf();
        $productsCollection->method('addAttributeToSelect')->willReturnSelf();
        $productsCollection->method('setPageSize')->willReturnSelf();
        $productsCollection->method('setCurPage')->willReturnSelf();
        $productsCollection->method('getSize')->willReturn(50);
        $productsCollection->method('getFirstItem')->willReturn($firstProduct);

        $productsFactory = $this->createMock(ProductCollectionFactory::class);
        $productsFactory->method('create')->willReturn($productsCollection);

        $imageHelper = $this->createMock(ImageHelper::class);
        $imageHelper->method('init')->willReturnSelf();
        $imageHelper->method('getUrl')->willReturn('https://shop.test/media/tops.jpg');

        $tiles = $this->viewModel($parent, $childrenFactory, $productsFactory, $imageHelper)->getItems();

        $this->assertSame([[
            'label' => 'Tops',
            'url' => 'https://shop.test/women/tops.html',
            'count' => 50,
            'image' => 'https://shop.test/media/tops.jpg',
        ]], $tiles);
    }

    public function testEmptyChildCategoryHasNoImage(): void
    {
        $parent = $this->createMock(Category::class);
        $parent->method('getId')->willReturn(20);

        $child = $this->createMock(Category::class);
        $child->method('getName')->willReturn('Bottoms');
        $child->method('getUrl')->willReturn('https://shop.test/women/bottoms.html');

        $childrenCollection = $this->createMock(CategoryCollection::class);
        $childrenCollection->method('addAttributeToSelect')->willReturnSelf();
        $childrenCollection->method('addAttributeToFilter')->willReturnSelf();
        $childrenCollection->method('setOrder')->willReturnSelf();
        $childrenCollection->method('getIterator')->willReturn(new \ArrayIterator([$child]));

        $childrenFactory = $this->createMock(CategoryCollectionFactory::class);
        $childrenFactory->method('create')->willReturn($childrenCollection);

        $productsCollection = $this->createMock(ProductCollection::class);
        $productsCollection->method('addCategoryFilter')->willReturnSelf();
        $productsCollection->method('setVisibility')->willReturnSelf();
        $productsCollection->method('addAttributeToSelect')->willReturnSelf();
        $productsCollection->method('getSize')->willReturn(0);

        $productsFactory = $this->createMock(ProductCollectionFactory::class);
        $productsFactory->method('create')->willReturn($productsCollection);

        $tiles = $this->viewModel($parent, $childrenFactory, $productsFactory)->getItems();

        $this->assertSame(0, $tiles[0]['count']);
        $this->assertSame('', $tiles[0]['image']);
    }

    public function testDescriptionIsRunThroughOutputFilter(): void
    {
        // getDescription() is a magic getter on Category, so it has to be added
        // to the mock explicitly rather than stubbed via createMock().
        $category = $this->getMockBuilder(Category::class)
            ->disableOriginalConstructor()
            ->addMethods(['getDescription'])
            ->getMock();
        $category->method('getDescription')->willReturn('<p>Cut clean.</p>');

        $output = $this->createMock(OutputHelper::class);
        $output->expects($this->once())
            ->method('categoryAttribute')
            ->with($category, '<p>Cut clean.</p>', 'description')
            ->willReturn('<p>Cut clean.</p>');

        $vm = $this->viewModel($category, null, null, null, $output);

        $this->assertSame('<p>Cut clean.</p>', $vm->getDescriptionHtml());
    }

    public function testNoDescriptionYieldsEmptyString(): void
    {
        $category = $this->getMockBuilder(Category::class)
            ->disableOriginalConstructor()
            ->addMethods(['getDescription'])
            ->getMock();
        $category->method('getDescription')->willReturn(null);

        $this->assertSame('', $this->viewModel($category)->getDescriptionHtml());
    }
}
