<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Checkout\ViewModel;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\CheckoutAgreements\Model\AgreementsConfigProvider;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use MageObsidian\Checkout\Api\VaultTokenProviderInterface;
use MageObsidian\Checkout\Model\Shell\Gate;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;
use MageObsidian\ModernFrontend\Model\Config\Source\CheckoutLayout;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

/**
 * Server-primed config for the checkout island.
 *
 * The Vue checkout replaces Magento's Knockout one-page, talking to the native
 * REST endpoints. This ViewModel hands the island everything it needs to render
 * the first step and authenticate REST calls WITHOUT a round-trip: the auth mode
 * (logged-in → `carts/mine` via session; guest → `guest-carts/:maskedId`), the
 * REST base, and a snapshot of the quote (items + totals) so the order summary
 * paints immediately. Any failure degrades to a safe, empty-ish config so the
 * page still renders.
 */
class CheckoutConfig implements ArgumentInterface
{
    /**
     * Memoised public payload (store-scoped; safe to inline into a cached page).
     *
     * @var array<string, mixed>|null
     */
    private ?array $publicConfig = null;

    /**
     * Memoised private payload (customer/quote-scoped).
     *
     * @var array<string, mixed>|null
     */
    private ?array $privateData = null;

    /**
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param StoreManagerInterface $storeManager
     * @param PriceCurrencyInterface $priceCurrency
     * @param QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param QuoteIdMaskResource $quoteIdMaskResource
     * @param CartItems $cartItems
     * @param VaultTokenProviderInterface $vaultTokenProvider
     * @param ConfigProvider $configProvider
     * @param ScopeConfigInterface $scopeConfig
     * @param AgreementsConfigProvider $agreementsConfigProvider
     * @param Gate $gate
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerSession $customerSession,
        private readonly StoreManagerInterface $storeManager,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        private readonly QuoteIdMaskFactory $quoteIdMaskFactory,
        private readonly QuoteIdMaskResource $quoteIdMaskResource,
        private readonly CartItems $cartItems,
        private readonly VaultTokenProviderInterface $vaultTokenProvider,
        private readonly ConfigProvider $configProvider,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly AgreementsConfigProvider $agreementsConfigProvider,
        private readonly Gate $gate
    ) {
    }

    /**
     * The full config payload the island mounts with (memoised).
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->getPublicConfig() + $this->getPrivateData();
    }

    /**
     * What the page writes into its HTML: the public half alone when cacheable,
     * since that is all that stands between this and a cart in the page cache.
     *
     * @return array<string, mixed>
     */
    public function getInlineConfig(): array
    {
        return $this->gate->isCacheable() ? $this->getPublicConfig() : $this->getConfig();
    }

    /**
     * The store-scoped half. Touches neither the quote nor the customer session:
     * a cached shell must not pay for, or start, one.
     *
     * @return array<string, mixed>
     */
    public function getPublicConfig(): array
    {
        if ($this->publicConfig === null) {
            try {
                $this->publicConfig = $this->buildPublic();
            } catch (Throwable) {
                $this->publicConfig = $this->emptyPublicConfig();
            }
        }

        return $this->publicConfig;
    }

    /**
     * The customer/quote-scoped half. `currencyFormat` belongs here because it
     * follows the request currency: keeping it out removes a vary dimension
     * rather than trusting one.
     *
     * @return array<string, mixed>
     */
    public function getPrivateData(): array
    {
        if ($this->privateData === null) {
            try {
                $this->privateData = $this->buildPrivate();
            } catch (Throwable) {
                $this->privateData = $this->emptyPrivateData();
            }
        }

        return $this->privateData;
    }

    /**
     * The config as a JSON string for the island props.
     *
     * @return string
     */
    public function getConfigJson(): string
    {
        return (string)json_encode($this->getConfig());
    }

    /**
     * Assemble the store-scoped half.
     *
     * @return array<string, mixed>
     */
    private function buildPublic(): array
    {
        $store = $this->storeManager->getStore();
        $baseUrl = (string)$store->getBaseUrl();
        $storeCode = (string)$store->getCode();

        return [
            'storeCode' => $storeCode,
            'currencyCode' => $this->currencyCode(),
            'baseUrl' => $baseUrl,
            'restBaseUrl' => $baseUrl . 'rest/' . $storeCode . '/V1/',
            'successUrl' => $baseUrl . 'checkout/onepage/success/',
            'layoutMode' => $this->configProvider->getCheckoutLayoutMode(),
            'guestCheckout' => $this->scopeConfig->isSetFlag('checkout/options/guest_checkout', ScopeInterface::SCOPE_STORE),
            'guestCheckoutLogin' => $this->scopeConfig->isSetFlag('checkout/options/enable_guest_checkout_login', ScopeInterface::SCOPE_STORE),
            'displayBillingOnPayment' => (int)$this->scopeConfig->getValue('checkout/options/display_billing_address_on', ScopeInterface::SCOPE_STORE) === 0,
            'maxSummaryItems' => (int)$this->scopeConfig->getValue('checkout/options/max_items_display_count', ScopeInterface::SCOPE_STORE) ?: 10,
            'agreements' => $this->agreements(),
        ];
    }

    /**
     * Assemble the customer/quote-scoped half.
     *
     * @return array<string, mixed>
     */
    private function buildPrivate(): array
    {
        $quote = $this->checkoutSession->getQuote();
        $isLoggedIn = $this->customerSession->isLoggedIn();

        return [
            'isLoggedIn' => $isLoggedIn,
            'maskedCartId' => $isLoggedIn ? '' : $this->maskedCartId($quote),
            'customerEmail' => $isLoggedIn ? $this->customerEmail() : '',
            'currencyFormat' => $this->currencyFormat(),
            'quote' => $this->quoteSummary($quote),
            'shipping' => $this->quoteShipping($quote),
            'vault' => $this->vaultTokens(),
            'addresses' => $isLoggedIn ? $this->addressBook() : [],
            'context' => $this->context(),
        ];
    }

    /**
     * @param Quote $quote
     * @return array{address: array<string, mixed>|null, method: array{carrier_code: string, method_code: string}, email: string}
     */
    private function quoteShipping(Quote $quote): array
    {
        $empty = ['address' => null, 'method' => ['carrier_code' => '', 'method_code' => ''], 'email' => ''];

        try {
            if ((bool)$quote->getIsVirtual()) {
                return $empty;
            }
            $address = $quote->getShippingAddress();
            $street = array_values(array_filter(
                (array)$address->getStreet(),
                static fn ($line): bool => trim((string)$line) !== ''
            ));
            if ((string)$address->getCountryId() === '' || $street === []) {
                return $empty;
            }

            return [
                'address' => [
                    'firstname' => (string)$address->getFirstname(),
                    'lastname' => (string)$address->getLastname(),
                    'company' => (string)$address->getCompany(),
                    'street' => $street,
                    'city' => (string)$address->getCity(),
                    'region' => (string)$address->getRegion(),
                    'regionId' => $address->getRegionId() ? (int)$address->getRegionId() : null,
                    'postcode' => (string)$address->getPostcode(),
                    'countryId' => (string)$address->getCountryId(),
                    'telephone' => (string)$address->getTelephone(),
                ],
                'method' => $this->shippingMethod((string)$address->getShippingMethod()),
                'email' => $this->quoteEmail($quote, $address),
            ];
        } catch (Throwable) {
            return $empty;
        }
    }

    /**
     * @param Quote $quote
     * @param mixed $shippingAddress
     * @return string
     */
    private function quoteEmail(Quote $quote, mixed $shippingAddress): string
    {
        $candidates = [
            (string)$quote->getCustomerEmail(),
            (string)$quote->getBillingAddress()?->getEmail(),
            (string)$shippingAddress->getEmail(),
        ];

        foreach ($candidates as $email) {
            if ($email !== '') {
                return $email;
            }
        }

        return '';
    }

    /**
     * @param string $method
     * @return array{carrier_code: string, method_code: string}
     */
    private function shippingMethod(string $method): array
    {
        if ($method === '') {
            return ['carrier_code' => '', 'method_code' => ''];
        }
        [$carrier, $code] = array_pad(explode('_', $method, 2), 2, '');

        return ['carrier_code' => $carrier, 'method_code' => $code];
    }

    /**
     * The customer's saved addresses, shaped like the client's AddressData.
     *
     * @return array<int, array<string, mixed>>
     */
    private function addressBook(): array
    {
        try {
            $customer = $this->customerSession->getCustomerData();
            if ($customer === null) {
                return [];
            }
            $defaultShipping = (string)$customer->getDefaultShipping();

            return array_values(array_map(
                fn (AddressInterface $address): array => $this->addressRow($address, $defaultShipping),
                $customer->getAddresses() ?? []
            ));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param AddressInterface $address
     * @param string $defaultShippingId
     * @return array<string, mixed>
     */
    private function addressRow(AddressInterface $address, string $defaultShippingId): array
    {
        $region = $address->getRegion();
        $regionName = $region ? (string)$region->getRegion() : '';
        $regionId = $region && $region->getRegionId() ? (int)$region->getRegionId() : null;
        $street = array_values(array_filter((array)$address->getStreet(), static fn ($line) => (string)$line !== ''));

        return [
            'id' => (int)$address->getId(),
            'label' => $this->addressLabel($address, $street, $regionName),
            'isDefaultShipping' => (string)$address->getId() === $defaultShippingId,
            'firstname' => (string)$address->getFirstname(),
            'lastname' => (string)$address->getLastname(),
            'company' => (string)$address->getCompany(),
            'street' => $street,
            'city' => (string)$address->getCity(),
            'region' => $regionName,
            'regionId' => $regionId,
            'postcode' => (string)$address->getPostcode(),
            'countryId' => (string)$address->getCountryId(),
            'telephone' => (string)$address->getTelephone(),
        ];
    }

    /**
     * One-line summary for the picker. Built here because the region name only
     * exists server-side.
     *
     * @param AddressInterface $address
     * @param array<int, string> $street
     * @param string $regionName
     * @return string
     */
    private function addressLabel(AddressInterface $address, array $street, string $regionName): string
    {
        $parts = [
            trim($address->getFirstname() . ' ' . $address->getLastname()),
            $street[0] ?? '',
            (string)$address->getCity(),
            trim($regionName . ' ' . (string)$address->getPostcode()),
        ];

        return implode(', ', array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    /**
     * Store and currency this payload was computed for. The version cookie only
     * moves on POST, and a currency or store switch is a GET.
     *
     * @return array{storeCode: string, currencyCode: string}
     */
    private function context(): array
    {
        try {
            $storeCode = (string)$this->storeManager->getStore()->getCode();
        } catch (Throwable) {
            $storeCode = '';
        }

        return ['storeCode' => $storeCode, 'currencyCode' => $this->currencyCode()];
    }

    /**
     * Currency the request is being served in, read from the HTTP context.
     *
     * @return string
     */
    private function currencyCode(): string
    {
        try {
            return (string)$this->storeManager->getStore()->getCurrentCurrencyCode();
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @return array{enabled: bool, items: array<int, array<string, mixed>>}
     */
    private function agreements(): array
    {
        try {
            $data = $this->agreementsConfigProvider->getConfig()['checkoutAgreements'] ?? [];
            return [
                'enabled' => (bool)($data['isEnabled'] ?? false),
                'items' => $data['agreements'] ?? [],
            ];
        } catch (Throwable) {
            return ['enabled' => false, 'items' => []];
        }
    }

    /**
     * The customer's saved cards for the payment step (empty without a configured
     * tokenizing gateway). Never blocks the checkout: failures degrade to none.
     *
     * @return array<int, array<string, mixed>>
     */
    private function vaultTokens(): array
    {
        try {
            return $this->vaultTokenProvider->getTokens();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Existing masked cart id for the guest quote, creating one if absent (REST
     * `guest-carts` endpoints are authorised by possession of this id).
     *
     * @param Quote $quote
     * @return string
     */
    private function maskedCartId(Quote $quote): string
    {
        $quoteId = (int)$quote->getId();
        if ($quoteId === 0) {
            return '';
        }

        try {
            $masked = $this->quoteIdToMaskedQuoteId->execute($quoteId);
        } catch (Throwable) {
            $masked = '';
        }
        if ($masked !== '') {
            return $masked;
        }

        // No mask yet (frontend session quote): create one. The resource model
        // generates the random masked id on save.
        $quoteIdMask = $this->quoteIdMaskFactory->create();
        $quoteIdMask->setQuoteId($quoteId);
        $this->quoteIdMaskResource->save($quoteIdMask);

        return (string)$quoteIdMask->getMaskedId();
    }

    /**
     * Currency output format (e.g. "$%s") for client-side price rendering.
     *
     * Lets the island format the shipping rates the same way the store does.
     *
     * @return string
     */
    private function currencyFormat(): string
    {
        try {
            return (string)$this->priceCurrency->getCurrency()->getOutputFormat();
        } catch (Throwable) {
            return '%s';
        }
    }

    /**
     * Logged-in customer's email, or '' on failure.
     *
     * @return string
     */
    private function customerEmail(): string
    {
        try {
            return (string)$this->customerSession->getCustomer()->getEmail();
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Quote snapshot for the order summary (reuses the bag-page row builder).
     *
     * @param Quote $quote
     * @return array<string, mixed>
     */
    private function quoteSummary(Quote $quote): array
    {
        $items = $this->cartItems->getItems();

        return [
            'items' => $items,
            'itemCount' => count($items),
            'subtotal' => (string)$this->priceCurrency->format((float)$quote->getSubtotal(), false),
            'grandTotal' => (string)$this->priceCurrency->format((float)$quote->getGrandTotal(), false),
        ];
    }

    /**
     * Safe fallback for the public half (renders an empty checkout rather than
     * failing).
     *
     * @return array<string, mixed>
     */
    private function emptyPublicConfig(): array
    {
        return [
            'storeCode' => '',
            'currencyCode' => '',
            'baseUrl' => '',
            'restBaseUrl' => '',
            'successUrl' => '',
            'layoutMode' => CheckoutLayout::STEPPED,
            'guestCheckout' => true,
            'guestCheckoutLogin' => false,
            'displayBillingOnPayment' => true,
            'maxSummaryItems' => 10,
            'agreements' => ['enabled' => false, 'items' => []],
        ];
    }

    /**
     * Safe fallback for the private half.
     *
     * @return array<string, mixed>
     */
    private function emptyPrivateData(): array
    {
        return [
            'isLoggedIn' => false,
            'maskedCartId' => '',
            'customerEmail' => '',
            'currencyFormat' => '%s',
            'quote' => ['items' => [], 'itemCount' => 0, 'subtotal' => '', 'grandTotal' => ''],
            'shipping' => ['address' => null, 'method' => ['carrier_code' => '', 'method_code' => ''], 'email' => ''],
            'vault' => [],
            'addresses' => [],
            'context' => ['storeCode' => '', 'currencyCode' => ''],
        ];
    }
}
