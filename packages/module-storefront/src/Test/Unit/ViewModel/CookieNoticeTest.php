<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use Magento\Cookie\Helper\Cookie as CookieHelper;
use Magento\Framework\UrlInterface;
use MageObsidian\Storefront\ViewModel\CookieNotice;
use PHPUnit\Framework\TestCase;

/**
 * The cookie-consent banner data source. We assert the enable gate mirrors the
 * restriction-mode flag, the website-ids payload + lifetime pass through, and the
 * privacy / no-cookies URLs route correctly. Needs Magento Cookie, so it skips
 * when that module is absent.
 */
class CookieNoticeTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(CookieHelper::class)) {
            $this->markTestSkipped('Magento Cookie is not available in this runtime.');
        }
    }

    private function helper(bool $enabled, string $idsJson = '{"1":1}', int $lifetime = 3600): CookieHelper
    {
        $helper = $this->createMock(CookieHelper::class);
        $helper->method('isCookieRestrictionModeEnabled')->willReturn($enabled);
        $helper->method('getAcceptedSaveCookiesWebsiteIds')->willReturn($idsJson);
        $helper->method('getCookieRestrictionLifetime')->willReturn($lifetime);

        return $helper;
    }

    private function url(): UrlInterface
    {
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn(string $route): string => 'https://shop.test/' . $route
        );

        return $url;
    }

    public function testIsEnabledMirrorsRestrictionMode(): void
    {
        $this->assertTrue((new CookieNotice($this->helper(true), $this->url()))->isEnabled());
        $this->assertFalse((new CookieNotice($this->helper(false), $this->url()))->isEnabled());
    }

    public function testPayloadPassesThrough(): void
    {
        $vm = new CookieNotice($this->helper(true, '{"1":1}', 7200), $this->url());

        $this->assertSame('{"1":1}', $vm->getWebsiteIdsJson());
        $this->assertSame(7200, $vm->getLifetime());
        $this->assertSame(CookieHelper::IS_USER_ALLOWED_SAVE_COOKIE, $vm->getCookieName());
    }

    public function testUrlsRouteThroughTheUrlBuilder(): void
    {
        $vm = new CookieNotice($this->helper(true), $this->url());

        $this->assertSame('https://shop.test/privacy-policy-cookie-restriction-mode', $vm->getPrivacyUrl());
        $this->assertSame('https://shop.test/cookie/index/noCookies', $vm->getNoCookiesUrl());
    }
}
