<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Model\Seo;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Storefront\Model\Config\SeoConfig;
use MageObsidian\Storefront\Model\Seo\CanonicalUrlBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The canonical is built from the store's own base URL plus the requested path,
 * never from the Host header, so a request that reaches the store through
 * another hostname still canonicalises to the configured one. Needs Magento
 * Store types, so it runs in a Magento root.
 */
class CanonicalUrlBuilderTest extends TestCase
{
    private StoreManagerInterface&MockObject $storeManager;
    private HttpRequest&MockObject $request;
    private SeoConfig&MockObject $config;
    private ScopeConfigInterface&MockObject $scopeConfig;

    protected function setUp(): void
    {
        if (!interface_exists(StoreManagerInterface::class)) {
            $this->markTestSkipped('Magento Store is not available in this runtime.');
        }
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->request = $this->createMock(HttpRequest::class);
        $this->config = $this->createMock(SeoConfig::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
    }

    /**
     * @param array<int, string> $keptParams
     */
    private function subject(
        string $baseUrl,
        string $requestUri,
        array $keptParams = ['p', 'q'],
        string $urlSuffix = '.html'
    ): CanonicalUrlBuilder {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn($baseUrl);
        $this->storeManager->method('getStore')->willReturn($store);
        $this->request->method('getRequestUri')->willReturn($requestUri);
        $this->config->method('getCanonicalQueryParams')->willReturn($keptParams);
        $this->scopeConfig->method('getValue')->willReturn($urlSuffix);

        return new CanonicalUrlBuilder(
            $this->storeManager,
            $this->request,
            $this->config,
            $this->scopeConfig
        );
    }

    public function testDropsTheFirstPageParameterSoItDoesNotDuplicateTheListing(): void
    {
        $subject = $this->subject('https://shop.test/', '/gear/bags.html?p=1');

        $this->assertSame('https://shop.test/gear/bags.html', $subject->build());
    }

    public function testKeepsPagesAfterTheFirst(): void
    {
        $subject = $this->subject('https://shop.test/', '/gear/bags.html?p=2');

        $this->assertSame('https://shop.test/gear/bags.html?p=2', $subject->build());
    }

    public function testDropsAKeptParameterThatCarriesNoValue(): void
    {
        $subject = $this->subject('https://shop.test/', '/catalogsearch/result/?q=');

        $this->assertSame('https://shop.test/catalogsearch/result', $subject->build());
    }

    public function testKeepsTheTrailingSlashWhenTheStoreUrlSuffixIsOne(): void
    {
        $subject = $this->subject('https://shop.test/', '/gear/bags/', ['p', 'q'], '/');

        $this->assertSame('https://shop.test/gear/bags/', $subject->build());
    }

    public function testBuildsFromTheStoreBaseUrlNotTheRequestHost(): void
    {
        $subject = $this->subject('https://shop.test/', '/gear/bags.html');

        $this->assertSame('https://shop.test/gear/bags.html', $subject->build());
    }

    public function testHomePageCanonicalisesToTheBaseUrl(): void
    {
        $this->assertSame('https://shop.test/', $this->subject('https://shop.test/', '/')->build());
    }

    public function testDropsTrackingParameters(): void
    {
        $subject = $this->subject(
            'https://shop.test/',
            '/gear/bags.html?utm_source=newsletter&gclid=abc&fbclid=def'
        );

        $this->assertSame('https://shop.test/gear/bags.html', $subject->build());
    }

    public function testKeepsTheAllowlistedPaginationParameter(): void
    {
        $subject = $this->subject('https://shop.test/', '/gear/bags.html?utm_source=x&p=3');

        $this->assertSame('https://shop.test/gear/bags.html?p=3', $subject->build());
    }

    public function testDropsLayeredNavigationFilters(): void
    {
        $subject = $this->subject('https://shop.test/', '/gear/bags.html?color=Blue&price=10-20');

        $this->assertSame('https://shop.test/gear/bags.html', $subject->build());
    }

    public function testKeepsNothingWhenTheAllowlistIsEmpty(): void
    {
        $subject = $this->subject('https://shop.test/', '/gear/bags.html?p=3', []);

        $this->assertSame('https://shop.test/gear/bags.html', $subject->build());
    }

    public function testStripsTheStoreCodeSegmentAlreadyCarriedByTheBaseUrl(): void
    {
        $subject = $this->subject('https://shop.test/fr/', '/fr/gear/bags.html');

        $this->assertSame('https://shop.test/fr/gear/bags.html', $subject->build());
    }

    public function testStripsASubdirectoryInstallPrefixOnlyOnce(): void
    {
        $subject = $this->subject('https://shop.test/magento/', '/magento/gear/bags.html');

        $this->assertSame('https://shop.test/magento/gear/bags.html', $subject->build());
    }

    public function testStoreRootUnderASubdirectoryCanonicalisesToTheBaseUrl(): void
    {
        $subject = $this->subject('https://shop.test/fr/', '/fr');

        $this->assertSame('https://shop.test/fr/', $subject->build());
    }

    public function testNormalisesATrailingSlash(): void
    {
        $subject = $this->subject('https://shop.test/', '/about-us/');

        $this->assertSame('https://shop.test/about-us', $subject->build());
    }

    public function testReturnsEmptyWhenTheStoreHasNoBaseUrl(): void
    {
        $this->assertSame('', $this->subject('', '/gear/bags.html')->build());
    }
}
