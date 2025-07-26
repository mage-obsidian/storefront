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
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
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
     * Memoised config payload.
     *
     * @var array<string, mixed>|null
     */
    private ?array $config = null;

    /**
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param StoreManagerInterface $storeManager
     * @param PriceCurrencyInterface $priceCurrency
     * @param QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param QuoteIdMaskResource $quoteIdMaskResource
     * @param CartItems $cartItems
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerSession $customerSession,
        private readonly StoreManagerInterface $storeManager,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        private readonly QuoteIdMaskFactory $quoteIdMaskFactory,
        private readonly QuoteIdMaskResource $quoteIdMaskResource,
        private readonly CartItems $cartItems
    ) {
    }

    /**
     * The full config payload the island mounts with (memoised).
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        if ($this->config !== null) {
            return $this->config;
        }

        try {
            $this->config = $this->build();
        } catch (Throwable) {
            $this->config = $this->emptyConfig();
        }

        return $this->config;
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
     * Assemble the config from the session quote and store context.
     *
     * @return array<string, mixed>
     */
    private function build(): array
    {
        $quote = $this->checkoutSession->getQuote();
        $store = $this->storeManager->getStore();
        $isLoggedIn = $this->customerSession->isLoggedIn();
        $baseUrl = (string)$store->getBaseUrl();
        $storeCode = (string)$store->getCode();

        return [
            'isLoggedIn' => $isLoggedIn,
            'maskedCartId' => $isLoggedIn ? '' : $this->maskedCartId($quote),
            'storeCode' => $storeCode,
            'baseUrl' => $baseUrl,
            'restBaseUrl' => $baseUrl . 'rest/' . $storeCode . '/V1/',
            'customerEmail' => $isLoggedIn ? $this->customerEmail() : '',
            'quote' => $this->quoteSummary($quote),
        ];
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
     * Safe fallback config (renders an empty checkout rather than failing).
     *
     * @return array<string, mixed>
     */
    private function emptyConfig(): array
    {
        return [
            'isLoggedIn' => false,
            'maskedCartId' => '',
            'storeCode' => '',
            'baseUrl' => '',
            'restBaseUrl' => '',
            'customerEmail' => '',
            'quote' => ['items' => [], 'itemCount' => 0, 'subtotal' => '', 'grandTotal' => ''],
        ];
    }
}
