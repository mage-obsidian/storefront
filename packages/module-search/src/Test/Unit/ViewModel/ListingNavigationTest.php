<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Search project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Search\Test\Unit\ViewModel;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\UrlInterface;
use MageObsidian\Search\Model\Config;
use MageObsidian\Search\Model\Fragment\RequestParameter;
use MageObsidian\Search\Model\Fragment\SectionPool;
use MageObsidian\Search\ViewModel\ListingNavigation;
use PHPUnit\Framework\TestCase;

class ListingNavigationTest extends TestCase
{
    private const string ACTION = 'catalog_category_view';
    private const array SECTIONS = [self::ACTION => ['listing' => 'category.products.list']];

    public function testItIsEnabledOnAServableListing(): void
    {
        $this->assertTrue($this->viewModel(true, self::ACTION, '')->isEnabled());
    }

    public function testItIsOffWhenTheFeatureIsDisabled(): void
    {
        $this->assertFalse($this->viewModel(false, self::ACTION, '')->isEnabled());
    }

    public function testASingleVisitCanOptOut(): void
    {
        $this->assertFalse($this->viewModel(true, self::ACTION, RequestParameter::VALUE_OFF)->isEnabled());
    }

    public function testItIsOffOnAPageWithNoConfiguredSections(): void
    {
        $this->assertFalse($this->viewModel(true, 'cms_index_index', '')->isEnabled());
    }

    public function testItPublishesTheNamesTheClientSharesWithPhp(): void
    {
        $viewModel = $this->viewModel(true, self::ACTION, '');

        $this->assertSame(RequestParameter::Fragment->value, $viewModel->getFragmentParameter());
        $this->assertSame(ListingNavigation::SECTION_ATTRIBUTE, $viewModel->getSectionAttribute());
    }

    /**
     * The path the controls are built against is not always the one being
     * browsed, so both go out and the client matches either.
     */
    public function testItPublishesBothTheGeneratedAndTheBrowsedPath(): void
    {
        $viewModel = $this->viewModel(
            true,
            'catalogsearch_result_index',
            '',
            'https://shop.test/catalogsearch/result/index/',
            '/catalogsearch/result/?q=jacket'
        );

        $this->assertSame(
            ['/catalogsearch/result/index/', '/catalogsearch/result/'],
            $viewModel->getListingPaths()
        );
    }

    public function testAPathIsPublishedOnceWhenBothResolveTheSame(): void
    {
        $viewModel = $this->viewModel(
            true,
            self::ACTION,
            '',
            'https://shop.test/men/tops-men.html',
            '/men/tops-men.html?color=59'
        );

        $this->assertSame(['/men/tops-men.html'], $viewModel->getListingPaths());
    }

    private function viewModel(
        bool $enabled,
        string $actionName,
        string $optOut,
        string $generatedUrl = 'https://shop.test/men/tops-men.html',
        string $requestUri = '/men/tops-men.html'
    ): ListingNavigation {
        $config = $this->createMock(Config::class);
        $config->method('areFragmentsEnabled')->willReturn($enabled);

        $request = $this->getMockBuilder(HttpRequest::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getFullActionName', 'getParam', 'getRequestUri'])
            ->getMock();
        $request->method('getFullActionName')->willReturn($actionName);
        $request->method('getParam')->willReturn($optOut);
        $request->method('getRequestUri')->willReturn($requestUri);

        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturn($generatedUrl);

        return new ListingNavigation($config, new SectionPool(self::SECTIONS), $request, $url);
    }
}
