<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use MageObsidian\Checkout\ViewModel\CartPager;
use PHPUnit\Framework\TestCase;

class CartPagerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(ScopeConfigInterface::class)) {
            $this->markTestSkipped('Magento Framework is not available in this runtime.');
        }
    }

    public function testKeepsEveryLineOnOnePageWhenTheyFit(): void
    {
        $pager = $this->pager('20', null);

        $this->assertSame([1, 2, 3], $pager->slice([1, 2, 3]));
        $this->assertSame([], $pager->getPages(3));
    }

    public function testSlicesTheLinesOfTheRequestedPage(): void
    {
        $rows = range(1, 5);

        $this->assertSame([1, 2], $this->pager('2', null)->slice($rows));
        $this->assertSame([3, 4], $this->pager('2', '2')->slice($rows));
        $this->assertSame([5], $this->pager('2', '3')->slice($rows));
    }

    public function testClampsAPageBeyondTheLastOne(): void
    {
        $this->assertSame([5], $this->pager('2', '9')->slice(range(1, 5)));
        $this->assertSame([1, 2], $this->pager('2', '0')->slice(range(1, 5)));
        $this->assertSame([1, 2], $this->pager('2', 'abc')->slice(range(1, 5)));
    }

    public function testListsOnePageLinkPerPageMarkingTheCurrentOne(): void
    {
        $pages = $this->pager('2', '2')->getPages(5);

        $this->assertSame([1, 2, 3], array_column($pages, 'number'));
        $this->assertSame([false, true, false], array_column($pages, 'current'));
        $this->assertSame('https://shop.test/cart?p=3', $pages[2]['url']);
    }

    public function testFallsBackToOneLinePerPageWhenTheSettingIsNotPositive(): void
    {
        $this->assertSame([1], $this->pager('0', null)->slice([1, 2, 3]));
    }

    private function pager(?string $perPage, ?string $page): CartPager
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn($perPage);
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn (string $name) => $name === 'p' ? $page : null);
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params): string => 'https://shop.test/cart?p=' . $params['_query']['p']
        );

        return new CartPager($config, $request, $url);
    }
}
