<?php
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Catalog\Test\Unit\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\CatalogSearch\Block\SearchTermsLog;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use MageObsidian\Catalog\ViewModel\ReportBeacons;
use PHPUnit\Framework\TestCase;

class ReportBeaconsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Product::class) || !class_exists(SearchTermsLog::class)) {
            $this->markTestSkipped('Magento Catalog and CatalogSearch are not available in this runtime.');
        }
    }

    private function viewModel(
        ?Product $product = null,
        array $flags = [],
        bool|\Throwable $cacheable = true
    ): ReportBeacons {
        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->with('current_product')->willReturn($product);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn(string $path): bool => $flags[$path] ?? true
        );

        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturnCallback(
            static fn(string $route): string => 'https://shop.test/' . $route
        );

        $searchTermsLog = $this->createMock(SearchTermsLog::class);
        if ($cacheable instanceof \Throwable) {
            $searchTermsLog->method('isPageCacheable')->willThrowException($cacheable);
        } else {
            $searchTermsLog->method('isPageCacheable')->willReturn($cacheable);
        }

        return new ReportBeacons($registry, $scopeConfig, $urlBuilder, $searchTermsLog);
    }

    private function product(int $id): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);

        return $product;
    }

    public function testProductViewCarriesTheEndpointAndTheProduct(): void
    {
        $this->assertSame(
            ['url' => 'https://shop.test/reports/report_product/view', 'productId' => '42'],
            $this->viewModel($this->product(42))->productView()
        );
    }

    public function testNoProductMeansNoProductView(): void
    {
        $this->assertNull($this->viewModel(null)->productView());
    }

    public function testReportsSwitchedOffMeanNoProductView(): void
    {
        $this->assertNull($this->viewModel($this->product(42), ['reports/options/enabled' => false])->productView());
    }

    public function testProductViewSwitchedOffMeansNoProductView(): void
    {
        $this->assertNull(
            $this->viewModel($this->product(42), ['reports/options/product_view_enabled' => false])->productView()
        );
    }

    public function testSearchTermsAreReportedFromACacheablePage(): void
    {
        $this->assertSame(
            ['url' => 'https://shop.test/catalogsearch/searchTermsLog/save'],
            $this->viewModel(null, [], true)->searchTerms()
        );
    }

    public function testSearchTermsAreLeftToTheControllerWhenThePageIsNotCacheable(): void
    {
        $this->assertNull($this->viewModel(null, [], false)->searchTerms());
    }

    public function testASearchTermsLogThatCannotAnswerMeansNoBeacon(): void
    {
        $this->assertNull($this->viewModel(null, [], new \RuntimeException('no header'))->searchTerms());
    }
}
