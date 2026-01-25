<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use Magento\Search\Helper\Data as SearchHelper;
use Magento\Search\ViewModel\ConfigProvider;
use MageObsidian\Storefront\ViewModel\SearchForm;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Feeds the header search box and autocomplete island store-aware URLs and the
 * query-length limits, delegating to Magento's native search helper/config (the
 * same backend Luma's mini form uses). Needs Magento Search types, so it runs in
 * a Magento root (see phpunit.ci.xml).
 */
class SearchFormTest extends TestCase
{
    private SearchHelper&MockObject $searchHelper;
    private ConfigProvider&MockObject $configProvider;

    protected function setUp(): void
    {
        if (!class_exists(SearchHelper::class)) {
            $this->markTestSkipped('Magento Search is not available in this runtime.');
        }
        $this->searchHelper = $this->createMock(SearchHelper::class);
        $this->configProvider = $this->createMock(ConfigProvider::class);
    }

    private function subject(): SearchForm
    {
        return new SearchForm($this->searchHelper, $this->configProvider);
    }

    public function testExposesNativeUrlsAndQueryParam(): void
    {
        $this->searchHelper->method('getResultUrl')->willReturn('https://shop.test/catalogsearch/result/');
        $this->searchHelper->method('getSuggestUrl')->willReturn('https://shop.test/search/ajax/suggest/');
        $this->searchHelper->method('getQueryParamName')->willReturn('q');
        $this->searchHelper->method('getEscapedQueryText')->willReturn('summer dress');

        $form = $this->subject();

        $this->assertSame('https://shop.test/catalogsearch/result/', $form->getActionUrl());
        $this->assertSame('https://shop.test/search/ajax/suggest/', $form->getSuggestUrl());
        $this->assertSame('q', $form->getQueryParam());
        $this->assertSame('summer dress', $form->getQueryValue());
    }

    public function testCastsQueryLengthLimitsToInt(): void
    {
        $this->searchHelper->method('getMinQueryLength')->willReturn('3');
        $this->searchHelper->method('getMaxQueryLength')->willReturn('128');

        $form = $this->subject();

        $this->assertSame(3, $form->getMinQueryLength());
        $this->assertSame(128, $form->getMaxQueryLength());
    }

    public function testReportsSuggestionsFlagFromConfig(): void
    {
        $this->configProvider->method('isSuggestionsAllowed')->willReturn(true);

        $this->assertTrue($this->subject()->isSuggestionsAllowed());
    }
}
