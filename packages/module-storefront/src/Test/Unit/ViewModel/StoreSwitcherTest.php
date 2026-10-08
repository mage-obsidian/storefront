<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Group;
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

    private function store(int $id, string $name, int $groupId = 1): Store&MockObject
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn($id);
        $store->method('getName')->willReturn($name);
        $store->method('getGroupId')->willReturn($groupId);
        $store->method('isActive')->willReturn(true);

        return $store;
    }

    /**
     * @param array<int, Store&MockObject> $stores
     */
    private function group(int $id, string $name, int $websiteId, int $defaultStoreId, array $stores): Group&MockObject
    {
        $group = $this->createMock(Group::class);
        $group->method('getId')->willReturn($id);
        $group->method('getName')->willReturn($name);
        $group->method('getWebsiteId')->willReturn($websiteId);
        $group->method('getDefaultStoreId')->willReturn($defaultStoreId);
        $group->method('getStores')->willReturn($stores);

        return $group;
    }

    private function inactiveStore(int $id, int $groupId): Store&MockObject
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn($id);
        $store->method('getGroupId')->willReturn($groupId);
        $store->method('isActive')->willReturn(false);

        return $store;
    }

    private function withGroups(array $groups, Store $current): void
    {
        $this->storeManager->method('getGroups')->willReturn($groups);
        $this->storeManager->method('getStore')->willReturn($current);
        $this->urlProvider->method('getTargetStoreRedirectUrl')
            ->willReturnCallback(static fn(StoreInterface $s): string => 'https://shop.test/switch/' . $s->getId());
    }

    private function websiteStore(int $id, int $groupId, int $websiteId = 1): Store&MockObject
    {
        $store = $this->store($id, 'View ' . $id, $groupId);
        $store->method('getWebsiteId')->willReturn($websiteId);

        return $store;
    }

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

    public function testExcludesStoreViewsFromOtherGroups(): void
    {
        $this->withStores([
            $this->store(1, 'English', 1),
            $this->store(2, 'Español', 1),
            $this->store(3, 'Internal', 2),
        ], 1);

        $items = $this->subject()->getItems();

        $this->assertCount(2, $items);
        $this->assertSame(['English', 'Español'], array_column($items, 'label'));
    }

    public function testListsTheGroupsOfTheCurrentWebsiteWithTheirDefaultView(): void
    {
        $main = $this->websiteStore(1, 1);
        $outletDefault = $this->websiteStore(5, 2);
        $outletOther = $this->websiteStore(6, 2);
        $this->withGroups([
            $this->group(1, 'Main', 1, 1, [$main]),
            $this->group(2, 'Outlet', 1, 5, [$outletOther, $outletDefault]),
        ], $main);

        $groups = $this->subject()->getGroups();

        $this->assertSame(['Main', 'Outlet'], array_column($groups, 'label'));
        $this->assertSame('https://shop.test/switch/5', $groups[1]['url']);
        $this->assertTrue($groups[0]['current']);
        $this->assertFalse($groups[1]['current']);
    }

    public function testFallsBackToAnActiveViewWhenTheDefaultOneIsInactive(): void
    {
        $main = $this->websiteStore(1, 1);
        $this->withGroups([
            $this->group(1, 'Main', 1, 1, [$main]),
            $this->group(2, 'Outlet', 1, 5, [$this->inactiveStore(5, 2), $this->websiteStore(6, 2)]),
        ], $main);

        $groups = $this->subject()->getGroups();

        $this->assertSame('https://shop.test/switch/6', $groups[1]['url']);
    }

    public function testSkipsGroupsWithoutActiveViewsAndGroupsOfOtherWebsites(): void
    {
        $main = $this->websiteStore(1, 1);
        $this->withGroups([
            $this->group(1, 'Main', 1, 1, [$main]),
            $this->group(2, 'Dormant', 1, 5, [$this->inactiveStore(5, 2)]),
            $this->group(3, 'Elsewhere', 2, 7, [$this->websiteStore(7, 3, 2)]),
        ], $main);

        $this->assertSame(['Main'], array_column($this->subject()->getGroups(), 'label'));
    }

    public function testHasMultipleGroupsOnlyWhenMoreThanOneGroupIsOffered(): void
    {
        $main = $this->websiteStore(1, 1);
        $this->withGroups([$this->group(1, 'Main', 1, 1, [$main])], $main);

        $this->assertFalse($this->subject()->hasMultipleGroups());
    }

    public function testHasMultipleGroupsWithTwoGroups(): void
    {
        $main = $this->websiteStore(1, 1);
        $this->withGroups([
            $this->group(1, 'Main', 1, 1, [$main]),
            $this->group(2, 'Outlet', 1, 5, [$this->websiteStore(5, 2)]),
        ], $main);

        $this->assertTrue($this->subject()->hasMultipleGroups());
    }

    public function testGroupsDegradeToEmptyWhenTheStoreManagerFails(): void
    {
        $this->storeManager->method('getStore')->willThrowException(new \RuntimeException('boom'));

        $this->assertSame([], $this->subject()->getGroups());
        $this->assertFalse($this->subject()->hasMultipleGroups());
    }
}
