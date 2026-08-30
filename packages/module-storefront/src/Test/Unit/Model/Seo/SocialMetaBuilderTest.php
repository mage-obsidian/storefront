<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Model\Seo;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Title;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Storefront\Model\Config\SeoConfig;
use MageObsidian\Storefront\Model\Seo\CanonicalUrl;
use MageObsidian\Storefront\Model\Seo\ClaimedSocialProperties;
use MageObsidian\Storefront\Model\Seo\CurrentEntity;
use MageObsidian\Storefront\Model\Seo\SocialMetaBuilder;
use MageObsidian\Storefront\Model\Seo\StoreMediaUrl;
use MageObsidian\Storefront\Model\Seo\TextSummarizer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * og:* and twitter:* derived from the page itself. The product page is the
 * interesting case: MageObsidian_Catalog already writes four og properties
 * there, so this must complement them instead of emitting a second set. Needs
 * Magento View/Store types, so it runs in a Magento root.
 */
class SocialMetaBuilderTest extends TestCase
{
    private PageConfig&MockObject $pageConfig;
    private StoreManagerInterface&MockObject $storeManager;
    private ScopeConfigInterface&MockObject $scopeConfig;
    private CanonicalUrl&MockObject $canonicalUrl;
    private CurrentEntity&MockObject $currentEntity;
    private StoreMediaUrl&MockObject $mediaUrl;
    private ClaimedSocialProperties&MockObject $claimed;
    private SeoConfig&MockObject $config;

    protected function setUp(): void
    {
        if (!class_exists(PageConfig::class)) {
            $this->markTestSkipped('Magento View is not available in this runtime.');
        }
        $this->pageConfig = $this->createMock(PageConfig::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->canonicalUrl = $this->createMock(CanonicalUrl::class);
        $this->currentEntity = $this->createMock(CurrentEntity::class);
        $this->mediaUrl = $this->createMock(StoreMediaUrl::class);
        $this->claimed = $this->createMock(ClaimedSocialProperties::class);
        $this->config = $this->createMock(SeoConfig::class);

        $title = $this->createMock(Title::class);
        $title->method('getShort')->willReturn('Joust Duffle Bag');
        $this->pageConfig->method('getTitle')->willReturn($title);
        $this->pageConfig->method('getDescription')->willReturn('A duffle bag for the gym.');

        $store = $this->createMock(Store::class);
        $store->method('getFrontendName')->willReturn('MageObsidian');
        $this->storeManager->method('getStore')->willReturn($store);
        $this->scopeConfig->method('getValue')->willReturn('en_US');

        $this->canonicalUrl->method('resolve')->willReturn('https://shop.test/joust-duffle-bag.html');
        $this->config->method('isSocialMetaEnabled')->willReturn(true);
        $this->config->method('getTwitterSite')->willReturn('');
        $this->config->method('getSocialImage')->willReturn('');
    }

    private function subject(): SocialMetaBuilder
    {
        return new SocialMetaBuilder(
            $this->pageConfig,
            $this->storeManager,
            $this->scopeConfig,
            $this->canonicalUrl,
            $this->currentEntity,
            $this->mediaUrl,
            new TextSummarizer(),
            $this->claimed,
            $this->config
        );
    }

    public function testEmitsTheFullSetOnAPageNobodyElseTouched(): void
    {
        $this->claimed->method('get')->willReturn([]);
        $this->currentEntity->method('getProduct')->willReturn(null);
        $this->currentEntity->method('getCategory')->willReturn(null);
        $this->mediaUrl->method('forFile')->willReturn('');
        $this->mediaUrl->method('getLogo')->willReturn('https://shop.test/media/logo/logo.png');

        $tags = $this->subject()->build();

        $this->assertSame('website', $tags['og:type']);
        $this->assertSame('MageObsidian', $tags['og:site_name']);
        $this->assertSame('en_US', $tags['og:locale']);
        $this->assertSame('Joust Duffle Bag', $tags['og:title']);
        $this->assertSame('A duffle bag for the gym.', $tags['og:description']);
        $this->assertSame('https://shop.test/joust-duffle-bag.html', $tags['og:url']);
        $this->assertSame('https://shop.test/media/logo/logo.png', $tags['og:image']);
        $this->assertSame('summary_large_image', $tags['twitter:card']);
        $this->assertSame('Joust Duffle Bag', $tags['twitter:title']);
        $this->assertSame('A duffle bag for the gym.', $tags['twitter:description']);
        $this->assertSame('https://shop.test/media/logo/logo.png', $tags['twitter:image']);
    }

    public function testDoesNotDuplicateThePropertiesTheCatalogBlockAlreadyEmits(): void
    {
        $this->claimed->method('get')->willReturn(['og:type', 'og:title', 'og:url', 'og:image']);
        $this->currentEntity->method('getProduct')->willReturn(new DataObject(['image' => '/j/o/joust.jpg']));
        $this->mediaUrl->method('getProductImage')->willReturn('https://shop.test/media/catalog/product/j/o/joust.jpg');

        $tags = $this->subject()->build();

        $this->assertArrayNotHasKey('og:type', $tags);
        $this->assertArrayNotHasKey('og:title', $tags);
        $this->assertArrayNotHasKey('og:url', $tags);
        $this->assertArrayNotHasKey('og:image', $tags);
        $this->assertSame('A duffle bag for the gym.', $tags['og:description']);
        $this->assertSame('MageObsidian', $tags['og:site_name']);
        $this->assertSame('en_US', $tags['og:locale']);
        $this->assertSame(
            'https://shop.test/media/catalog/product/j/o/joust.jpg',
            $tags['twitter:image']
        );
    }

    public function testTypeIsProductOnAProductPage(): void
    {
        $this->claimed->method('get')->willReturn([]);
        $this->currentEntity->method('getProduct')->willReturn(new DataObject(['image' => '/j/o/joust.jpg']));
        $this->mediaUrl->method('getProductImage')->willReturn('https://shop.test/media/catalog/product/j/o/joust.jpg');

        $this->assertSame('product', $this->subject()->build()['og:type']);
    }

    public function testUsesTheCategoryImageOnACategoryPage(): void
    {
        $this->claimed->method('get')->willReturn([]);
        $this->currentEntity->method('getProduct')->willReturn(null);
        $category = new class (['image_url' => 'https://shop.test/media/catalog/category/bags.jpg']) extends DataObject {
            public function getImageUrl(): string
            {
                return (string)$this->getData('image_url');
            }
        };
        $this->currentEntity->method('getCategory')->willReturn($category);

        $this->assertSame(
            'https://shop.test/media/catalog/category/bags.jpg',
            $this->subject()->build()['og:image']
        );
    }

    public function testOmitsTagsWithNothingBehindThem(): void
    {
        $this->claimed->method('get')->willReturn([]);
        $this->currentEntity->method('getProduct')->willReturn(null);
        $this->currentEntity->method('getCategory')->willReturn(null);
        $this->mediaUrl->method('forFile')->willReturn('');
        $this->mediaUrl->method('getLogo')->willReturn('');

        $tags = $this->subject()->build();

        $this->assertArrayNotHasKey('og:image', $tags);
        $this->assertArrayNotHasKey('twitter:image', $tags);
        $this->assertArrayNotHasKey('twitter:site', $tags);
    }

    public function testEmitsNothingWhenTheMerchantTurnedItOff(): void
    {
        $config = $this->createMock(SeoConfig::class);
        $config->method('isSocialMetaEnabled')->willReturn(false);

        $subject = new SocialMetaBuilder(
            $this->pageConfig,
            $this->storeManager,
            $this->scopeConfig,
            $this->canonicalUrl,
            $this->currentEntity,
            $this->mediaUrl,
            new TextSummarizer(),
            $this->claimed,
            $config
        );

        $this->assertSame([], $subject->build());
    }
}
