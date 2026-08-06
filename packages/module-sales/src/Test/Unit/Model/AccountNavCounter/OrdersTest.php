<?php
declare(strict_types=1);

namespace MageObsidian\Sales\Test\Unit\Model\AccountNavCounter;

use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use MageObsidian\Customer\Api\AccountNavCounterInterface;
use MageObsidian\Sales\Model\AccountNavCounter\Orders;
use PHPUnit\Framework\TestCase;

/**
 * The badge next to "My Orders" in the account rail. It must count exactly what
 * the history page lists — the statuses visible on the front — and stay quiet
 * for a session with no customer.
 */
class OrdersTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(CollectionFactory::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    /**
     * @param array<int, string> $filters Captured addFieldToFilter calls, by field
     */
    private function buildCounter(int $customerId, int $size, array &$filters = []): Orders
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')
            ->willReturnCallback(function (string $field, $condition) use ($collection, &$filters) {
                $filters[$field] = $condition;
                return $collection;
            });
        $collection->method('getSize')->willReturn($size);

        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $orderConfig = $this->createMock(OrderConfig::class);
        $orderConfig->method('getVisibleOnFrontStatuses')->willReturn(['processing', 'complete']);

        $currentCustomer = $this->createMock(CurrentCustomer::class);
        $currentCustomer->method('getCustomerId')->willReturn($customerId);

        return new Orders($factory, $orderConfig, $currentCustomer);
    }

    public function testCountsTheCustomersOrders(): void
    {
        $this->assertSame(7, $this->buildCounter(3, 7)->getCount());
    }

    public function testCountsOnlyTheStatusesVisibleOnTheFront(): void
    {
        $filters = [];
        $this->buildCounter(3, 7, $filters)->getCount();

        $this->assertSame(3, $filters['customer_id']);
        $this->assertSame(['in' => ['processing', 'complete']], $filters['status']);
    }

    public function testReportsNoCountForAGuestSession(): void
    {
        $this->assertNull($this->buildCounter(0, 7)->getCount());
    }

    public function testHonoursTheAccountNavCounterContract(): void
    {
        $this->assertInstanceOf(AccountNavCounterInterface::class, $this->buildCounter(3, 0));
    }
}
