<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Storefront\ViewModel\CurrencySwitcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Currency switcher data source. Lists the store's allowed currencies, flags the
 * active one, and builds the native `directory/currency/switch` GET URL (with a
 * uenc return param). The switch controller is a GET action, so no POST/form-key
 * is needed. Needs Magento Store types, so it runs in a Magento root.
 */
class CurrencySwitcherTest extends TestCase
{
    private StoreManagerInterface&MockObject $storeManager;
    private UrlInterface&MockObject $url;
    private EncoderInterface&MockObject $encoder;

    protected function setUp(): void
    {
        if (!interface_exists(StoreManagerInterface::class)) {
            $this->markTestSkipped('Magento Store is not available in this runtime.');
        }
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->url = $this->createMock(UrlInterface::class);
        $this->encoder = $this->createMock(EncoderInterface::class);
    }

    /**
     * @param array<int, string> $available
     */
    private function withCurrencies(array $available, string $current): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getCurrentCurrencyCode')->willReturn($current);
        $store->method('getAvailableCurrencyCodes')->willReturn($available);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->url->method('getCurrentUrl')->willReturn('https://shop.test/page');
        $this->encoder->method('encode')->willReturn('ENC');
        $this->url->method('getUrl')->willReturnCallback(
            static fn(string $route, array $params): string
                => 'https://shop.test/' . $route . '?currency=' . ($params['_query']['currency'] ?? '')
        );
    }

    private function subject(): CurrencySwitcher
    {
        return new CurrencySwitcher($this->storeManager, $this->url, $this->encoder);
    }

    public function testMapsAllowedCurrenciesAndFlagsCurrent(): void
    {
        $this->withCurrencies(['USD', 'EUR'], 'USD');

        $items = $this->subject()->getItems();

        $this->assertCount(2, $items);
        $this->assertSame('USD', $items[0]['label']);
        $this->assertTrue($items[0]['current']);
        $this->assertSame('EUR', $items[1]['label']);
        $this->assertFalse($items[1]['current']);
        $this->assertStringContainsString('currency=EUR', $items[1]['url']);
    }

    public function testCurrentLabelAndHasMultiple(): void
    {
        $this->withCurrencies(['USD', 'EUR'], 'EUR');

        $subject = $this->subject();

        $this->assertSame('EUR', $subject->getCurrentLabel());
        $this->assertTrue($subject->hasMultiple());
    }

    public function testHasMultipleIsFalseForSingleCurrency(): void
    {
        $this->withCurrencies(['USD'], 'USD');

        $this->assertFalse($this->subject()->hasMultiple());
    }
}
