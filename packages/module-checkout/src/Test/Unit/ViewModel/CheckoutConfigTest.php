<?php
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\ViewModel;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\CheckoutAgreements\Model\AgreementsConfigProvider;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Checkout\Api\VaultTokenProviderInterface;
use MageObsidian\Checkout\ViewModel\CartItems;
use MageObsidian\Checkout\ViewModel\CheckoutConfig;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use PHPUnit\Framework\TestCase;

/**
 * Server-primed checkout config. We assert the auth mode (logged-in → mine,
 * guest → masked id), the REST base URL and the quote snapshot; needs Magento
 * Quote/Store types, so it runs in a Magento root. The mask-creation path and
 * REST authentication are validated end-to-end in the browser.
 */
class CheckoutConfigTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(QuoteIdToMaskedQuoteIdInterface::class)) {
            $this->markTestSkipped('Magento Quote is not available in this runtime.');
        }
    }

    public function testGuestConfigCarriesTheMaskedCartId(): void
    {
        $config = $this->viewModel(isLoggedIn: false, maskedId: 'guestmask123')->getConfig();

        $this->assertFalse($config['isLoggedIn']);
        $this->assertSame('guestmask123', $config['maskedCartId']);
        $this->assertSame('', $config['customerEmail']);
        $this->assertSame('https://shop.test/rest/default/V1/', $config['restBaseUrl']);
        $this->assertSame(2, $config['quote']['itemCount']);
        $this->assertSame('$80.00', $config['quote']['subtotal']);
        $this->assertSame('$88.00', $config['quote']['grandTotal']);
        $this->assertSame('$%s', $config['currencyFormat']);
        $this->assertSame('braintree_cc_vault', $config['vault'][0]['methodCode']);
        $this->assertSame('stepped', $config['layoutMode']);
    }

    public function testConfigCarriesTheConfiguredCheckoutLayout(): void
    {
        $config = $this->viewModel(isLoggedIn: false, maskedId: 'guestmask123', layoutMode: 'onepage')->getConfig();

        $this->assertSame('onepage', $config['layoutMode']);
    }

    public function testLoggedInConfigOmitsTheMaskAndCarriesEmail(): void
    {
        $config = $this->viewModel(isLoggedIn: true, maskedId: 'unused')->getConfig();

        $this->assertTrue($config['isLoggedIn']);
        $this->assertSame('', $config['maskedCartId']);
        $this->assertSame('ada@shop.test', $config['customerEmail']);
    }

    public function testConfigCarriesNativeCheckoutOptionsAndAgreements(): void
    {
        $config = $this->viewModel(
            isLoggedIn: false,
            maskedId: 'guestmask123',
            agreementsEnabled: true,
            agreementItems: [['agreementId' => 7, 'mode' => 1, 'content' => 'Terms', 'checkboxText' => 'I agree', 'contentHeight' => '']]
        )->getConfig();

        $this->assertTrue($config['guestCheckout']);
        $this->assertFalse($config['guestCheckoutLogin']);
        $this->assertTrue($config['displayBillingOnPayment']);
        $this->assertSame(25, $config['maxSummaryItems']);
        $this->assertTrue($config['agreements']['enabled']);
        $this->assertSame(7, $config['agreements']['items'][0]['agreementId']);
    }

    private function viewModel(
        bool $isLoggedIn,
        string $maskedId,
        string $layoutMode = 'stepped',
        bool $agreementsEnabled = false,
        array $agreementItems = []
    ): CheckoutConfig {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->addMethods(['getSubtotal', 'getGrandTotal'])
            ->onlyMethods(['getId'])
            ->getMock();
        $quote->method('getId')->willReturn(42);
        $quote->method('getSubtotal')->willReturn(80.0);
        $quote->method('getGrandTotal')->willReturn(88.0);

        $checkoutSession = $this->createMock(CheckoutSession::class);
        $checkoutSession->method('getQuote')->willReturn($quote);

        $customer = $this->getMockBuilder(Customer::class)
            ->disableOriginalConstructor()
            ->addMethods(['getEmail'])
            ->getMock();
        $customer->method('getEmail')->willReturn('ada@shop.test');
        $customerSession = $this->createMock(CustomerSession::class);
        $customerSession->method('isLoggedIn')->willReturn($isLoggedIn);
        $customerSession->method('getCustomer')->willReturn($customer);

        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.test/');
        $store->method('getCode')->willReturn('default');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $currency = $this->getMockBuilder(\stdClass::class)->addMethods(['getOutputFormat'])->getMock();
        $currency->method('getOutputFormat')->willReturn('$%s');
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->method('format')->willReturnCallback(
            static fn ($amount): string => '$' . number_format((float)$amount, 2)
        );
        $priceCurrency->method('getCurrency')->willReturn($currency);

        $quoteIdToMaskedQuoteId = $this->createMock(QuoteIdToMaskedQuoteIdInterface::class);
        $quoteIdToMaskedQuoteId->method('execute')->willReturn($maskedId);

        $cartItems = $this->createMock(CartItems::class);
        $cartItems->method('getItems')->willReturn([
            ['id' => 1, 'name' => 'A'],
            ['id' => 2, 'name' => 'B'],
        ]);

        $vaultTokenProvider = $this->createMock(VaultTokenProviderInterface::class);
        $vaultTokenProvider->method('getTokens')->willReturn([
            ['publicHash' => 'h1', 'methodCode' => 'braintree_cc_vault', 'last4' => '1111', 'type' => 'VI', 'typeLabel' => 'Visa', 'expiration' => '12/2030'],
        ]);

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('getCheckoutLayoutMode')->willReturn($layoutMode);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path): bool => $path === 'checkout/options/guest_checkout'
        );
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): ?string => $path === 'checkout/options/max_items_display_count' ? '25' : '0'
        );

        $agreementsConfigProvider = $this->createMock(AgreementsConfigProvider::class);
        $agreementsConfigProvider->method('getConfig')->willReturn([
            'checkoutAgreements' => ['isEnabled' => $agreementsEnabled, 'agreements' => $agreementItems],
        ]);

        return new CheckoutConfig(
            $checkoutSession,
            $customerSession,
            $storeManager,
            $priceCurrency,
            $quoteIdToMaskedQuoteId,
            $this->createMock(QuoteIdMaskFactory::class),
            $this->createMock(QuoteIdMaskResource::class),
            $cartItems,
            $vaultTokenProvider,
            $configProvider,
            $scopeConfig,
            $agreementsConfigProvider
        );
    }
}
