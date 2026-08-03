<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Checkout\Test\Unit;

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
use MageObsidian\Checkout\Model\Shell\Gate;
use MageObsidian\Checkout\ViewModel\CartItems;
use MageObsidian\Checkout\ViewModel\CheckoutConfig;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;

/**
 * Builds a real CheckoutConfig over mocked Magento collaborators.
 *
 * Shared so the customer-data section can be exercised against the real view
 * model instead of a stub: wiring the section to `getConfig()` rather than
 * `getPrivateData()` has to make a test fail, and only a real instance does that.
 */
trait CheckoutConfigFixture
{
    private function checkoutConfig(
        bool $isLoggedIn,
        string $maskedId,
        string $layoutMode = 'stepped',
        bool $agreementsEnabled = false,
        array $agreementItems = [],
        bool $shellCacheable = false
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

        $gate = $this->createMock(Gate::class);
        $gate->method('isCacheable')->willReturn($shellCacheable);

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
            $agreementsConfigProvider,
            $gate
        );
    }
}
