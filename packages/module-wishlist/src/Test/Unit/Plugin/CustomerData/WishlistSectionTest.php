<?php
declare(strict_types=1);

namespace MageObsidian\Wishlist\Test\Unit\Plugin\CustomerData;

use Magento\Framework\UrlInterface;
use Magento\Wishlist\CustomerData\Wishlist;
use Magento\Wishlist\Helper\Data as WishlistHelper;
use MageObsidian\Wishlist\Plugin\CustomerData\WishlistSection;
use PHPUnit\Framework\TestCase;

/**
 * Attaches `saved` (product id → remove url) to the wishlist section so the heart
 * reflects every membership beyond the native three-item cap. Needs Magento
 * Wishlist types, so it runs in a Magento root.
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

        return new WishlistSection($helper, $url);
    }

    public function testAttachesSavedMapToTheNativePayload(): void
    {
        $plugin = $this->subject([$this->item(12, 5), $this->item(34, 9)]);

        $result = $plugin->afterGetSectionData(
            $this->createMock(Wishlist::class),
            ['counter' => '2 items', 'items' => []]
        );

        $this->assertSame('2 items', $result['counter']);
        $this->assertSame([
            12 => '/wishlist/index/remove/item/5/',
            34 => '/wishlist/index/remove/item/9/',
        ], $result['saved']);
    }

    public function testSavedIsEmptyWhenTheCollectionThrows(): void
    {
        $helper = $this->createMock(WishlistHelper::class);
        $helper->method('getWishlistItemCollection')
            ->willThrowException(new \RuntimeException('no customer'));
        $plugin = new WishlistSection($helper, $this->createMock(UrlInterface::class));

        $result = $plugin->afterGetSectionData($this->createMock(Wishlist::class), ['items' => []]);

        $this->assertSame([], $result['saved']);
    }
}
