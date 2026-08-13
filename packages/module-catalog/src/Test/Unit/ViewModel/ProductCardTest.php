<?php
declare(strict_types=1);

namespace MageObsidian\Catalog\Test\Unit\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Checkout\Helper\Cart as CartHelper;
use Magento\Framework\Url\Helper\Data as UrlHelper;
use MageObsidian\Catalog\ViewModel\ProductCard;
use PHPUnit\Framework\TestCase;

/**
 * Per-product presentation logic for the reusable product card. Keeps the type
 * decision (quick-add vs choose-options) out of the template and testable: a
 * product is quick-addable only when it is saleable AND needs no configuration
 * (canConfigure() === false — plain simple/virtual). Composite types and
 * options-bearing products route to the PDP. Needs Magento Catalog types, so it
 * runs in a Magento root (see phpunit.ci.xml).
 */
class ProductCardTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Product::class)) {
            $this->markTestSkipped('Magento Catalog is not available in this runtime.');
        }
    }

    private function card(
        ?CartHelper $cartHelper = null,
        ?UrlHelper $urlHelper = null,
        ?ImageHelper $imageHelper = null
    ): ProductCard {
        return new ProductCard(
            $cartHelper ?? $this->createMock(CartHelper::class),
            $urlHelper ?? $this->createMock(UrlHelper::class),
            $imageHelper ?? $this->createMock(ImageHelper::class)
        );
    }

    /**
     * The helper is stateful: init() returns itself and resize() mutates it, so
     * the double answers a fresh URL per resize call in the order they arrive.
     *
     * @param list<string> $resizedUrls
     */
    private function imageHelper(array $resizedUrls, string $baseUrl = 'https://acme.test/480.jpg'): ImageHelper
    {
        $helper = $this->createMock(ImageHelper::class);
        $helper->method('init')->willReturnSelf();
        $helper->method('resize')->willReturnSelf();
        $helper->method('getWidth')->willReturn(480);
        $helper->method('getHeight')->willReturn(600);
        $helper->method('getLabel')->willReturn('Joust Duffle Bag');

        $urls = $resizedUrls;
        $helper->method('getUrl')->willReturnCallback(
            static function () use (&$urls, $baseUrl): string {
                return array_shift($urls) ?? $baseUrl;
            }
        );

        return $helper;
    }

    private function product(bool $saleable, bool $canConfigure, string $requiredOptions = '0'): Product
    {
        $type = $this->createMock(AbstractType::class);
        $type->method('canConfigure')->willReturn($canConfigure);

        $product = $this->createMock(Product::class);
        $product->method('isSaleable')->willReturn($saleable);
        $product->method('getTypeInstance')->willReturn($type);
        $product->method('getData')->with('required_options')->willReturn($requiredOptions);

        return $product;
    }

    public function testSimpleSaleableProductIsQuickAdd(): void
    {
        // Plain simple/virtual: saleable and nothing to configure.
        $this->assertTrue($this->card()->isQuickAdd($this->product(true, false)));
    }

    public function testConfigurableProductIsNotQuickAdd(): void
    {
        // Configurable/bundle/grouped (or a simple with required options) →
        // choose options on the PDP instead. canConfigure() is true even though
        // Configurable::isPossibleBuyFromList() would lie and return true.
        $this->assertFalse($this->card()->isQuickAdd($this->product(true, true)));
    }

    public function testOutOfStockProductIsNotQuickAdd(): void
    {
        $this->assertFalse($this->card()->isQuickAdd($this->product(false, false)));
    }

    public function testSimpleWithRequiredOptionsIsNotQuickAddOnListing(): void
    {
        // On a listing the collection has no options loaded, so canConfigure() is
        // false; the required_options flag (in the default listing select) is what
        // keeps the broken quick-add off the card.
        $this->assertFalse($this->card()->isQuickAdd($this->product(true, false, '1')));
    }

    public function testAddToCartPostParamsBuildActionAndData(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(42);

        $cartHelper = $this->createMock(CartHelper::class);
        $cartHelper->method('getAddUrl')->willReturn('https://shop.test/checkout/cart/add/product/42/');
        $urlHelper = $this->createMock(UrlHelper::class);
        $urlHelper->method('getEncodedUrl')->willReturn('ENC');

        $params = $this->card($cartHelper, $urlHelper)->getAddToCartPostParams($product);

        $this->assertSame('https://shop.test/checkout/cart/add/product/42/', $params['action']);
        $this->assertSame(42, $params['data']['product']);
        $this->assertSame('ENC', $params['data']['uenc']);
    }

    public function testAddToCartPostParamsIsEmptyWhenUrlBuildFails(): void
    {
        $cartHelper = $this->createMock(CartHelper::class);
        $cartHelper->method('getAddUrl')->willThrowException(new \RuntimeException('boom'));

        $this->assertSame([], $this->card($cartHelper)->getAddToCartPostParams($this->createMock(Product::class)));
    }

    public function testGetImageReturnsTheRenditionAndOneCandidatePerWidth(): void
    {
        $card = $this->card(null, null, $this->imageHelper([
            'https://acme.test/base.jpg',
            'https://acme.test/240.jpg',
            'https://acme.test/320.jpg',
            'https://acme.test/480.jpg',
        ]));

        $image = $card->getImage($this->product(true, false), 'category_page_grid');

        $this->assertSame('https://acme.test/base.jpg', $image['src']);
        $this->assertSame(480, $image['width']);
        $this->assertSame(600, $image['height']);
        $this->assertSame('Joust Duffle Bag', $image['label']);
        $this->assertSame(
            'https://acme.test/240.jpg 240w, https://acme.test/320.jpg 320w, https://acme.test/480.jpg 480w',
            $image['srcset']
        );
    }

    public function testGetImageHonoursExplicitWidthsAndSortsThemAscending(): void
    {
        $card = $this->card(null, null, $this->imageHelper([
            'https://acme.test/base.jpg',
            'https://acme.test/800.jpg',
            'https://acme.test/400.jpg',
        ]));

        $image = $card->getImage($this->product(true, false), 'product_page_image_large', [800, 400]);

        $this->assertSame(
            'https://acme.test/400.jpg 400w, https://acme.test/800.jpg 800w',
            $image['srcset']
        );
    }

    public function testGetImageDropsRepeatedAndNonPositiveWidths(): void
    {
        $card = $this->card(null, null, $this->imageHelper([
            'https://acme.test/base.jpg',
            'https://acme.test/320.jpg',
        ]));

        $image = $card->getImage($this->product(true, false), 'category_page_grid', [320, 320, 0, -100]);

        $this->assertSame('https://acme.test/320.jpg 320w', $image['srcset']);
    }

    public function testGetImageSkipsACandidateWithNoUrl(): void
    {
        $card = $this->card(null, null, $this->imageHelper([
            'https://acme.test/base.jpg',
            '',
            'https://acme.test/320.jpg',
        ]));

        $image = $card->getImage($this->product(true, false), 'category_page_grid', [240, 320]);

        $this->assertSame('https://acme.test/320.jpg 320w', $image['srcset']);
    }
}
