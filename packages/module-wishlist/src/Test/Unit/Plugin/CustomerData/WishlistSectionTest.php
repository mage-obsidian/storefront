<?php
/**
 * This file is part of the MageObsidian - Wishlist project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
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
        return new class ($productId, $itemId) {
            public function __construct(private readonly int $productId, private readonly int $itemId)
            {
            }

            public function getProductId(): int
            {
                return $this->productId;
            }

            public function getId(): int
            {
                return $this->itemId;
            }
        };
    }

    private function collection(array $items): object
    {
        return new class ($items) implements \IteratorAggregate {
            public mixed $pageSize = 3;

            public function __construct(private readonly array $items)
            {
            }

            public function clear(): self
            {
                return $this;
            }

            public function setPageSize(mixed $size): self
            {
                $this->pageSize = $size;

                return $this;
            }

            public function getSelect(): object
            {
                return new class () {
                    public function reset(string $part): self
                    {
                        return $this;
                    }
                };
            }

            public function getIterator(): \Traversable
            {
                return new \ArrayIterator($this->pageSize === 3 ? array_slice($this->items, 0, 3) : $this->items);
            }
        };
    }

    private function subject(array $items): WishlistSection
    {
        $helper = $this->createMock(WishlistHelper::class);
        $helper->method('getWishlistItemCollection')->willReturn($this->collection($items));

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

    public function testListsEverySavedProductEvenWhenTheSidebarCappedTheSharedCollection(): void
    {
        $items = [];
        foreach (range(1, 8) as $index) {
            $items[] = $this->item($index * 10, $index);
        }

        $result = $this->subject($items)->afterGetSectionData($this->createMock(Wishlist::class), ['items' => []]);

        $this->assertCount(8, $result['saved']);
        $this->assertArrayHasKey(80, $result['saved']);
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
