<?php
declare(strict_types=1);

namespace MageObsidian\GiftMessage\Test\Unit\ViewModel;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\GiftMessage\Helper\Message as GiftMessageHelper;
use Magento\Quote\Model\Quote;
use MageObsidian\Checkout\ViewModel\CheckoutConfig;
use MageObsidian\GiftMessage\ViewModel\GiftOptions;
use PHPUnit\Framework\TestCase;

/**
 * Cart gift-options VM. We assert the config toggles read the native xpaths, the
 * endpoint config is forwarded from the checkout island, and saved messages are
 * resolved off the quote / its items.
 */
class GiftOptionsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(GiftMessageHelper::class) || !class_exists(CheckoutConfig::class)) {
            $this->markTestSkipped('Magento GiftMessage / MageObsidian Checkout not available in this runtime.');
        }
    }

    private function make(
        ?ScopeConfigInterface $scopeConfig = null,
        ?CheckoutSession $checkoutSession = null,
        ?GiftMessageHelper $helper = null,
        ?CheckoutConfig $checkoutConfig = null
    ): GiftOptions {
        return new GiftOptions(
            $scopeConfig ?? $this->createMock(ScopeConfigInterface::class),
            $checkoutSession ?? $this->createMock(CheckoutSession::class),
            $helper ?? $this->createMock(GiftMessageHelper::class),
            $checkoutConfig ?? $this->createMock(CheckoutConfig::class)
        );
    }

    public function testTogglesReadTheNativeXpaths(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnMap([
            ['sales/gift_options/allow_order', 'store', null, true],
            ['sales/gift_options/allow_items', 'store', null, false],
        ]);

        $view = $this->make($scopeConfig);

        $this->assertTrue($view->isOrderAllowed());
        $this->assertFalse($view->isItemsAllowed());
        $this->assertTrue($view->isAvailable());
    }

    public function testNotAvailableWhenBothDisabled(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn(false);

        $this->assertFalse($this->make($scopeConfig)->isAvailable());
    }

    public function testEndpointConfigForwardsTheCheckoutIslandConfig(): void
    {
        $checkoutConfig = $this->createMock(CheckoutConfig::class);
        $checkoutConfig->method('getConfig')->willReturn([
            'restBaseUrl' => 'https://shop.test/rest/default/V1/',
            'isLoggedIn' => true,
            'maskedCartId' => '',
            'other' => 'ignored',
        ]);

        $this->assertSame(
            ['restBaseUrl' => 'https://shop.test/rest/default/V1/', 'isLoggedIn' => true, 'maskedCartId' => ''],
            $this->make(null, null, null, $checkoutConfig)->getEndpointConfig()
        );
    }

    public function testOrderMessageResolvesFromTheQuote(): void
    {
        // getGiftMessageId is a magic getter on Quote → addMethods.
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->addMethods(['getGiftMessageId'])
            ->getMock();
        $quote->method('getGiftMessageId')->willReturn(5);
        $session = $this->createMock(CheckoutSession::class);
        $session->method('getQuote')->willReturn($quote);

        $helper = $this->createMock(GiftMessageHelper::class);
        $helper->method('getGiftMessage')
            ->willReturn(new DataObject(['sender' => 'Jean', 'recipient' => 'Alex', 'message' => 'Hi']));

        $this->assertSame(
            ['sender' => 'Jean', 'recipient' => 'Alex', 'message' => 'Hi'],
            $this->make(null, $session, $helper)->getOrderMessage()
        );
    }

    public function testItemMessageIsEmptyForUnknownItem(): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getItemById')->willReturn(null);
        $session = $this->createMock(CheckoutSession::class);
        $session->method('getQuote')->willReturn($quote);

        $this->assertSame(
            ['sender' => '', 'recipient' => '', 'message' => ''],
            $this->make(null, $session)->getItemMessage(999)
        );
    }
}
