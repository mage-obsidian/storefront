<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel\Head;

use Magento\Framework\App\Rss\DataProviderInterface;
use Magento\Framework\App\Rss\RssManagerInterface;
use MageObsidian\Storefront\ViewModel\Head\RssLinks;
use PHPUnit\Framework\TestCase;

class RssLinksTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(RssManagerInterface::class)) {
            $this->markTestSkipped('Magento Rss is not available in this runtime.');
        }
    }

    private function provider(array $feeds): DataProviderInterface
    {
        $provider = $this->createStub(DataProviderInterface::class);
        $provider->method('getFeeds')->willReturn($feeds);

        return $provider;
    }

    private function viewModel(DataProviderInterface ...$providers): RssLinks
    {
        $manager = $this->createStub(RssManagerInterface::class);
        $manager->method('getProviders')->willReturn($providers);

        return new RssLinks($manager);
    }

    public function testEachActiveFeedBecomesALink(): void
    {
        $links = $this->viewModel(
            $this->provider(['label' => 'New Products', 'link' => 'https://shop.test/rss/feed/index/type/new_products']),
            $this->provider(['label' => 'Special Products', 'link' => 'https://shop.test/rss/feed/index/type/special_products'])
        )->all();

        $this->assertSame(
            [
                ['label' => 'New Products', 'href' => 'https://shop.test/rss/feed/index/type/new_products'],
                ['label' => 'Special Products', 'href' => 'https://shop.test/rss/feed/index/type/special_products'],
            ],
            $links
        );
    }

    public function testAGroupOfFeedsIsFlattened(): void
    {
        $links = $this->viewModel(
            $this->provider([
                'group' => 'Categories',
                'feeds' => [
                    ['label' => 'Women', 'link' => 'https://shop.test/rss/feed/index/type/category/cid/20'],
                    ['label' => 'Men', 'link' => 'https://shop.test/rss/feed/index/type/category/cid/11'],
                ],
            ])
        )->all();

        $this->assertSame(['Women', 'Men'], array_column($links, 'label'));
    }

    public function testAProviderWithNothingActiveContributesNothing(): void
    {
        $this->assertSame([], $this->viewModel($this->provider([]))->all());
    }

    public function testAStoreWithoutProvidersHasNoLinks(): void
    {
        $this->assertSame([], $this->viewModel()->all());
    }
}
