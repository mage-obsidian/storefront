<?php
declare(strict_types=1);

namespace MageObsidian\Review\Test\Unit\ViewModel;

use Magento\Framework\Registry;
use Magento\Review\Model\ResourceModel\Review\Collection;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Review\ViewModel\ProductReviews;
use PHPUnit\Framework\TestCase;

/**
 * PDP review list ViewModel. Asserts it maps the approved-review collection (with
 * each review's average vote percentage) and derives the aggregate, and that it
 * degrades to empty off a product page. Needs Magento Review/Store types, so it
 * runs in a Magento root.
 */
class ProductReviewsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Collection::class)) {
            $this->markTestSkipped('Magento Review is not available in this runtime.');
        }
    }

    private function vote(int $percent): object
    {
        return new class ($percent) {
            public function __construct(private readonly int $percent)
            {
            }

            public function getPercent(): int
            {
                return $this->percent;
            }
        };
    }

    private function review(string $title, array $votePercents): object
    {
        return new class ($title, array_map(fn ($p) => $this->vote($p), $votePercents)) {
            public function __construct(private readonly string $title, private readonly array $votes)
            {
            }

            public function getTitle(): string
            {
                return $this->title;
            }

            public function getDetail(): string
            {
                return 'Body';
            }

            public function getNickname(): string
            {
                return 'Ada';
            }

            public function getCreatedAt(): string
            {
                return '2026-06-21 00:00:00';
            }

            public function getRatingVotes(): array
            {
                return $this->votes;
            }
        };
    }

    private function subject(?object $product, array $reviews): ProductReviews
    {
        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->with('current_product')->willReturn($product);

        $collection = $this->createMock(Collection::class);
        foreach (['addStoreFilter', 'addStatusFilter', 'addEntityFilter', 'setDateOrder', 'addRateVotes'] as $m) {
            $collection->method($m)->willReturnSelf();
        }
        $collection->method('getIterator')->willReturn(new \ArrayIterator($reviews));

        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new ProductReviews($registry, $factory, $storeManager);
    }

    private function product(): object
    {
        return new class {
            public function getId(): int
            {
                return 682;
            }

            public function getName(): string
            {
                return 'Helios Tank';
            }

            public function getProductUrl(): string
            {
                return 'https://shop.test/helios.html';
            }
        };
    }

    public function testMapsApprovedReviewsWithAverageVotePercent(): void
    {
        $vm = $this->subject($this->product(), [
            $this->review('Great', [100, 80]),
            $this->review('Good', [60, 60]),
        ]);

        $items = $vm->getItems();

        $this->assertCount(2, $items);
        $this->assertSame('Great', $items[0]['title']);
        $this->assertSame(90, $items[0]['percent']);
        $this->assertSame(60, $items[1]['percent']);
        $this->assertTrue($vm->hasReviews());
        $this->assertSame(2, $vm->getCount());
    }

    public function testDerivesAggregateAcrossReviews(): void
    {
        $vm = $this->subject($this->product(), [
            $this->review('A', [100]),
            $this->review('B', [50]),
        ]);

        $this->assertSame(75, $vm->getAveragePercent());
        $this->assertSame(3.8, $vm->getAverageStars());
        $this->assertSame('Helios Tank', $vm->getProductName());
    }

    public function testDegradesToEmptyOffAProductPage(): void
    {
        $vm = $this->subject(null, []);

        $this->assertSame([], $vm->getItems());
        $this->assertFalse($vm->hasReviews());
        $this->assertSame(0, $vm->getAveragePercent());
        $this->assertSame('', $vm->getProductName());
    }
}
