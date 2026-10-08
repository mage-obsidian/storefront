<?php
/**
 * This file is part of the MageObsidian - Multishipping project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Multishipping\Test\Unit\ViewModel;

use Magento\Catalog\Helper\Product\Configuration\ConfigurationInterface;
use Magento\Catalog\Helper\Product\ConfigurationPool;
use Magento\Quote\Model\Quote\Address\Item as AddressItem;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use MageObsidian\Multishipping\ViewModel\LineOptions;
use PHPUnit\Framework\TestCase;

class LineOptionsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(ConfigurationPool::class)) {
            $this->markTestSkipped('Magento Catalog is not available in this runtime.');
        }
    }

    public function testListsTheOptionsAQuoteItemWasConfiguredWith(): void
    {
        $item = $this->quoteItem('configurable');
        $viewModel = new LineOptions($this->poolReturning('configurable', $item, [
            ['label' => 'Size', 'value' => 'XS'],
            ['label' => 'Color', 'value' => 'Black'],
        ]));

        $this->assertSame(
            [['label' => 'Size', 'value' => 'XS'], ['label' => 'Color', 'value' => 'Black']],
            $viewModel->getOptions($item)
        );
    }

    public function testReadsTheQuoteItemBehindAnAddressItem(): void
    {
        $quoteItem = $this->quoteItem('simple');
        $addressItem = new class ($quoteItem) extends AddressItem {
            public function __construct(private readonly QuoteItem $behind)
            {
            }

            public function getQuoteItem(): QuoteItem
            {
                return $this->behind;
            }
        };
        $viewModel = new LineOptions($this->poolReturning('simple', $quoteItem, [
            ['label' => 'Engraving', 'value' => 'Obsidian'],
        ]));

        $this->assertSame([['label' => 'Engraving', 'value' => 'Obsidian']], $viewModel->getOptions($addressItem));
    }

    public function testJoinsMultipleChoicesAndDropsMarkup(): void
    {
        $item = $this->quoteItem('bundle');
        $viewModel = new LineOptions($this->poolReturning('bundle', $item, [
            ['label' => 'Strap', 'value' => ['6 foot', '<b>8 foot</b>']],
        ]));

        $this->assertSame([['label' => 'Strap', 'value' => '6 foot, 8 foot']], $viewModel->getOptions($item));
    }

    public function testListsNothingWhenTheCatalogCannotDescribeTheItem(): void
    {
        $item = $this->quoteItem('simple');
        $pool = $this->createMock(ConfigurationPool::class);
        $pool->method('getByProductType')->willThrowException(new \InvalidArgumentException('unknown type'));

        $this->assertSame([], (new LineOptions($pool))->getOptions($item));
    }

    private function quoteItem(string $type): QuoteItem
    {
        $item = $this->getMockBuilder(QuoteItem::class)->disableOriginalConstructor()
            ->onlyMethods(['getProductType'])->getMock();
        $item->method('getProductType')->willReturn($type);

        return $item;
    }

    private function poolReturning(string $type, QuoteItem $item, array $options): ConfigurationPool
    {
        $configuration = $this->createMock(ConfigurationInterface::class);
        $configuration->method('getOptions')->with($item)->willReturn($options);
        $pool = $this->createMock(ConfigurationPool::class);
        $pool->method('getByProductType')->with($type)->willReturn($configuration);

        return $pool;
    }
}
