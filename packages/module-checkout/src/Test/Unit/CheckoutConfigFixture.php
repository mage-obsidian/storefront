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
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\RegionInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Directory\Model\Currency;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
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
        bool $shellCacheable = false,
        array $addresses = [],
        ?string $defaultShippingId = null,
        array $quoteShipping = []
    ): CheckoutConfig {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getIsVirtual', 'getShippingAddress', 'getBillingAddress'])
            ->getMock();
        $quote->method('getId')->willReturn(42);
        $quote->method('getIsVirtual')->willReturn($quoteShipping['isVirtual'] ?? false);
        $quote->method('getShippingAddress')->willReturn($this->quoteAddressMock($quoteShipping));
        $quote->method('getBillingAddress')->willReturn(
            $this->quoteAddressMock(['addressEmail' => $quoteShipping['billingEmail'] ?? ''])
        );
        $quote->setData([
            'subtotal' => 80.0,
            'grand_total' => 88.0,
            'customer_email' => $quoteShipping['email'] ?? '',
        ]);

        $checkoutSession = $this->createMock(CheckoutSession::class);
        $checkoutSession->method('getQuote')->willReturn($quote);

        $customer = new DataObject(['email' => 'ada@shop.test']);
        $customerSession = $this->createMock(CustomerSession::class);
        $customerSession->method('isLoggedIn')->willReturn($isLoggedIn);
        $customerSession->method('getCustomer')->willReturn($customer);

        $customerData = $this->createMock(CustomerInterface::class);
        $customerData->method('getAddresses')->willReturn(
            array_map(fn (array $row) => $this->addressMock($row), $addresses)
        );
        $customerData->method('getDefaultShipping')->willReturn($defaultShippingId);
        $customerSession->method('getCustomerData')->willReturn($addresses === [] ? null : $customerData);

        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.test/');
        $store->method('getCode')->willReturn('default');
        $store->method('getCurrentCurrencyCode')->willReturn('USD');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $currency = $this->createMock(Currency::class);
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

    /**
     * @param array<string, mixed> $row
     */
    private function quoteAddressMock(array $row): QuoteAddress
    {
        $address = $this->createMock(QuoteAddress::class);
        $address->method('getFirstname')->willReturn($row['firstname'] ?? '');
        $address->method('getLastname')->willReturn($row['lastname'] ?? '');
        $address->method('getCompany')->willReturn($row['company'] ?? '');
        $address->method('getStreet')->willReturn($row['street'] ?? []);
        $address->method('getCity')->willReturn($row['city'] ?? '');
        $address->method('getRegion')->willReturn($row['region'] ?? '');
        $address->method('getRegionId')->willReturn($row['regionId'] ?? null);
        $address->method('getPostcode')->willReturn($row['postcode'] ?? '');
        $address->method('getCountryId')->willReturn($row['countryId'] ?? '');
        $address->method('getTelephone')->willReturn($row['telephone'] ?? '');
        $address->method('getShippingMethod')->willReturn($row['method'] ?? '');
        $address->method('getEmail')->willReturn($row['addressEmail'] ?? '');

        return $address;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function addressMock(array $row): AddressInterface
    {
        $region = $this->createMock(RegionInterface::class);
        $region->method('getRegion')->willReturn($row['region'] ?? '');
        $region->method('getRegionId')->willReturn(isset($row['regionId']) ? (string)$row['regionId'] : null);

        $address = $this->createMock(AddressInterface::class);
        $address->method('getId')->willReturn((int)$row['id']);
        $address->method('getFirstname')->willReturn($row['firstname'] ?? '');
        $address->method('getLastname')->willReturn($row['lastname'] ?? '');
        $address->method('getCompany')->willReturn($row['company'] ?? '');
        $address->method('getStreet')->willReturn($row['street'] ?? []);
        $address->method('getCity')->willReturn($row['city'] ?? '');
        $address->method('getRegion')->willReturn($region);
        $address->method('getPostcode')->willReturn($row['postcode'] ?? '');
        $address->method('getCountryId')->willReturn($row['countryId'] ?? '');
        $address->method('getTelephone')->willReturn($row['telephone'] ?? '');

        return $address;
    }
}
