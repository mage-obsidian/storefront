<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use Magento\Framework\App\RequestInterface;
use Magento\Search\Helper\Data as SearchHelper;
use Magento\Search\Model\Query;
use Magento\Search\Model\QueryFactory;
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
    private QueryFactory&MockObject $queryFactory;
    private RequestInterface&MockObject $request;

    protected function setUp(): void
    {
        if (!class_exists(SearchHelper::class)) {
            $this->markTestSkipped('Magento Search is not available in this runtime.');
        }
        $this->searchHelper = $this->createMock(SearchHelper::class);
        $this->configProvider = $this->createMock(ConfigProvider::class);
        $this->queryFactory = $this->createMock(QueryFactory::class);
        $this->request = $this->createMock(RequestInterface::class);
    }

    private function queryText(string $text): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getQueryText')->willReturn($text);
        $this->queryFactory->method('get')->willReturn($query);
    }

    private function subject(): SearchForm
    {
        return new SearchForm($this->searchHelper, $this->configProvider, $this->queryFactory, $this->request);
    }

    public function testExposesNativeUrlsAndQueryParam(): void
    {
        $this->searchHelper->method('getResultUrl')->willReturn('https://shop.test/catalogsearch/result/');
        $this->searchHelper->method('getSuggestUrl')->willReturn('https://shop.test/search/ajax/suggest/');
        $this->searchHelper->method('getQueryParamName')->willReturn('q');
        $this->request->method('getParam')->with('q')->willReturn('summer dress');
        $this->queryText('summer dress');

        $form = $this->subject();

        $this->assertSame('https://shop.test/catalogsearch/result/', $form->getActionUrl());
        $this->assertSame('https://shop.test/search/ajax/suggest/', $form->getSuggestUrl());
        $this->assertSame('q', $form->getQueryParam());
        $this->assertSame('summer dress', $form->getQueryValue());
    }

    public function testReturnsTheTermUnescapedSoTheTemplateEscapesItOnce(): void
    {
        $this->searchHelper->method('getQueryParamName')->willReturn('q');
        $this->request->method('getParam')->with('q')->willReturn('Ben & Jerry');
        $this->queryText('Ben & Jerry');

        $this->assertSame('Ben & Jerry', $this->subject()->getQueryValue());
    }

    public function testReturnsNothingWithoutAQueryOnTheRequest(): void
    {
        foreach (['missing' => null, 'blank' => '   ', 'array' => ['a', 'b']] as $case => $param) {
            $this->setUp();
            $this->searchHelper->method('getQueryParamName')->willReturn('q');
            $this->request->method('getParam')->with('q')->willReturn($param);
            $this->queryFactory->expects($this->never())->method('get');

            $this->assertSame('', $this->subject()->getQueryValue(), $case);
        }
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
