<?php
declare(strict_types=1);

namespace MageObsidian\Wishlist\Test\Unit\Model\AccountNavCounter;

use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Wishlist\Model\ResourceModel\Item\Collection;
use Magento\Wishlist\Model\Wishlist as WishlistModel;
use Magento\Wishlist\Model\WishlistFactory;
use MageObsidian\Customer\Api\AccountNavCounterInterface;
use MageObsidian\Wishlist\Model\AccountNavCounter\Wishlist;
use PHPUnit\Framework\TestCase;

/**
 * The badge next to "My Wish List" in the account rail.
 */
class WishlistTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(WishlistFactory::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    private function buildCounter(int $customerId, int $size = 0): Wishlist
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('getSize')->willReturn($size);

        $wishlist = $this->createMock(WishlistModel::class);
        $wishlist->method('loadByCustomerId')->willReturnSelf();
        $wishlist->method('getItemCollection')->willReturn($collection);

        $factory = $this->createMock(WishlistFactory::class);
        $factory->method('create')->willReturn($wishlist);

        $currentCustomer = $this->createMock(CurrentCustomer::class);
        $currentCustomer->method('getCustomerId')->willReturn($customerId);

        return new Wishlist($factory, $currentCustomer);
    }

    public function testReportsTheItemCount(): void
    {
        $this->assertSame(4, $this->buildCounter(7, 4)->getCount());
    }

    public function testReportsZeroForAnEmptyWishlist(): void
    {
        $this->assertSame(0, $this->buildCounter(7, 0)->getCount());
    }

    /**
     * A guest has no rail, but the counter is injected as a Proxy and must not
     * invent a badge if something does reach it.
     */
    public function testReportsNothingForAGuest(): void
    {
        $this->assertNull($this->buildCounter(0)->getCount());
    }

    public function testHonoursTheAccountNavCounterContract(): void
    {
        $this->assertInstanceOf(AccountNavCounterInterface::class, $this->buildCounter(7));
    }
}
