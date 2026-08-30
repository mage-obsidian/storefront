<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel\Head;

use Magento\Framework\UrlInterface;
use MageObsidian\Storefront\Model\Config\SeoConfig;
use MageObsidian\Storefront\Model\Seo\CanonicalUrl;
use MageObsidian\Storefront\ViewModel\Head\SeoLinks;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The two head links Magento has no page asset for. Needs Magento URL types, so
 * it runs in a Magento root.
 */
class SeoLinksTest extends TestCase
{
    private CanonicalUrl&MockObject $canonicalUrl;
    private UrlInterface&MockObject $url;
    private SeoConfig&MockObject $config;

    protected function setUp(): void
    {
        if (!interface_exists(UrlInterface::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
        $this->canonicalUrl = $this->createMock(CanonicalUrl::class);
        $this->url = $this->createMock(UrlInterface::class);
        $this->config = $this->createMock(SeoConfig::class);
    }

    private function subject(): SeoLinks
    {
        return new SeoLinks($this->canonicalUrl, $this->url, $this->config);
    }

    public function testExposesTheCanonicalThisModuleOwns(): void
    {
        $this->canonicalUrl->method('getOwn')->willReturn('https://shop.test/about-us');

        $this->assertSame('https://shop.test/about-us', $this->subject()->getCanonicalUrl());
    }

    public function testExposesNoCanonicalWhenSomebodyElseOwnsIt(): void
    {
        $this->canonicalUrl->method('getOwn')->willReturn('');

        $this->assertSame('', $this->subject()->getCanonicalUrl());
    }

    public function testDegradesToNoCanonicalRatherThanBreakingTheHead(): void
    {
        $this->canonicalUrl->method('getOwn')->willThrowException(new RuntimeException('no store'));

        $this->assertSame('', $this->subject()->getCanonicalUrl());
    }

    public function testBuildsTheManifestUrlOnTheModuleRoute(): void
    {
        $this->config->method('isManifestEnabled')->willReturn(true);
        $this->url->method('getUrl')
            ->with(SeoLinks::MANIFEST_ROUTE, ['_secure' => true])
            ->willReturn('https://shop.test/mage-obsidian-storefront/manifest');

        $this->assertSame(
            'https://shop.test/mage-obsidian-storefront/manifest',
            $this->subject()->getManifestUrl()
        );
    }

    public function testEmitsNoManifestLinkWhenTheMerchantTurnedItOff(): void
    {
        $this->config->method('isManifestEnabled')->willReturn(false);
        $this->url->expects($this->never())->method('getUrl');

        $this->assertSame('', $this->subject()->getManifestUrl());
    }
}
