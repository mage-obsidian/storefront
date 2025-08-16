<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\ViewModel\SwitcherUrlProvider;
use MageObsidian\Storefront\ViewModel\StoreSwitcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Language/store switcher data source. Lists the active store views with the
 * redirect URL Magento itself uses (SwitcherUrlProvider), and flags the current
 * one — so the header and mobile menu share a single source. Needs Magento Store
 * types, so it runs in a Magento root (see phpunit.ci.xml).
 */
class StoreSwitcherTest extends TestCase
{
    private StoreManagerInterface&MockObject $storeManager;
    private SwitcherUrlProvider&MockObject $urlProvider;

    protected function setUp(): void
    {
        if (!interface_exists(StoreManagerInterface::class)) {
            $this->markTestSkipped('Magento Store is not available in this runtime.');
        }
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->urlProvider = $this->createMock(SwitcherUrlProvider::class);
    }

    private function store(int $id, string $name): Store&MockObject
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn($id);
        $store->method('getName')->willReturn($name);
        $store->method('isActive')->willReturn(true);

        return $store;
    }

    /**
     * @param array<int, Store&MockObject> $stores
     */
    private function withStores(array $stores, int $currentId): void
    {
        $byId = [];
        foreach ($stores as $store) {
            $byId[$store->getId()] = $store;
        }
        $this->storeManager->method('getStores')->willReturn($stores);
        $this->storeManager->method('getStore')->willReturn($byId[$currentId]);
        $this->urlProvider->method('getTargetStoreRedirectUrl')
            ->willReturnCallback(static fn(StoreInterface $s): string => 'https://shop.test/switch/' . $s->getId());
    }

    private function subject(): StoreSwitcher
    {
        return new StoreSwitcher($this->storeManager, $this->urlProvider);
    }

    public function testMapsActiveStoresToItemsAndFlagsCurrent(): void
    {
        $this->withStores([$this->store(1, 'English'), $this->store(2, 'Español')], 1);

        $items = $this->subject()->getItems();

        $this->assertCount(2, $items);
        $this->assertSame('English', $items[0]['label']);
        $this->assertSame('https://shop.test/switch/1', $items[0]['url']);
        $this->assertTrue($items[0]['current']);
        $this->assertFalse($items[1]['current']);
    }

    public function testHasMultipleAndCurrentLabel(): void
    {
        $this->withStores([$this->store(1, 'English'), $this->store(2, 'Español')], 2);

        $subject = $this->subject();

        $this->assertTrue($subject->hasMultiple());
        $this->assertSame('Español', $subject->getCurrentLabel());
    }

    public function testHasMultipleIsFalseForSingleStore(): void
    {
        $this->withStores([$this->store(1, 'English')], 1);

        $this->assertFalse($this->subject()->hasMultiple());
    }
}
