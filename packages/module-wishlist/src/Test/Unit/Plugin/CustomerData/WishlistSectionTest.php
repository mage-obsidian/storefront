<?php
declare(strict_types=1);

namespace MageObsidian\Wishlist\Test\Unit\Plugin\CustomerData;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use Magento\Wishlist\CustomerData\Wishlist;
use Magento\Wishlist\Helper\Data as WishlistHelper;
use MageObsidian\Wishlist\Plugin\CustomerData\WishlistSection;
use PHPUnit\Framework\TestCase;

/**
 * Attaches `saved` (product id → remove url) to the wishlist section and, since
 * the native items render prices through the suppressed price-render block, falls
 * back to a price-free payload when the section build throws. Needs Magento
 * Wishlist/Store types, so it runs in a Magento root.
 */
class WishlistSectionTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Wishlist::class)) {
            $this->markTestSkipped('Magento Wishlist is not available in this runtime.');
        }
    }

    private function item(int $productId, int $itemId): object
    {
        $item = $this->getMockBuilder(\stdClass::class)->addMethods(['getProductId', 'getId'])->getMock();
        $item->method('getProductId')->willReturn($productId);
        $item->method('getId')->willReturn($itemId);

        return $item;
    }

    private function subject(array $items): WishlistSection
    {
        $helper = $this->createMock(WishlistHelper::class);
        $helper->method('getWishlistItemCollection')->willReturn($items);

        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params): string => "/$route/item/{$params['item']}/"
        );

        $website = $this->createMock(Website::class);
        $website->method('getId')->willReturn(1);
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getWebsite')->willReturn($website);
        $storeManager->method('getStore')->willReturn($store);

        return new WishlistSection($helper, $url, $storeManager);
    }

    public function testAttachesSavedMapToTheNativePayload(): void
    {
        $plugin = $this->subject([$this->item(12, 5), $this->item(34, 9)]);

        $result = $plugin->aroundGetSectionData(
            $this->createMock(Wishlist::class),
            static fn (): array => ['counter' => '2 items', 'items' => []]
        );

        $this->assertSame('2 items', $result['counter']);
        $this->assertSame([
            12 => '/wishlist/index/remove/item/5/',
            34 => '/wishlist/index/remove/item/9/',
        ], $result['saved']);
    }

    public function testFallsBackToAPriceFreePayloadWhenTheSectionThrows(): void
    {
        $plugin = $this->subject([$this->item(7, 3)]);

        $result = $plugin->aroundGetSectionData(
            $this->createMock(Wishlist::class),
            static function (): array {
                throw new \RuntimeException('Wrong Price Rendering layout configuration');
            }
        );

        $this->assertSame([], $result['items']);
        $this->assertSame(1, $result['websiteId']);
        $this->assertSame([7 => '/wishlist/index/remove/item/3/'], $result['saved']);
    }
}
