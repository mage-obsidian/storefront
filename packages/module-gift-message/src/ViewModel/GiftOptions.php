<?php
/**
 * This file is part of the MageObsidian - GiftMessage project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\GiftMessage\ViewModel;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\GiftMessage\Helper\Message as GiftMessageHelper;
use Magento\Store\Model\ScopeInterface;
use MageObsidian\Checkout\ViewModel\CheckoutConfig;
use Throwable;

/**
 * Gift-options data for the cart editor island.
 *
 * MageObsidian suppresses the core gift-message frontend (Knockout UI), so this
 * re-exposes what the Twig + Vue island need: whether order-level and per-item
 * messages are enabled, the REST endpoint config (reused from the checkout
 * island so cart-path/auth resolution stays in one place), and any already-saved
 * message so the editor prefills. The island posts to the native
 * `carts/(mine|guest-carts/:id)/gift-message[/:itemId]` endpoints.
 */
class GiftOptions implements ArgumentInterface
{
    private const XPATH_ALLOW_ORDER = 'sales/gift_options/allow_order';
    private const XPATH_ALLOW_ITEMS = 'sales/gift_options/allow_items';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param CheckoutSession $checkoutSession
     * @param GiftMessageHelper $giftMessageHelper
     * @param CheckoutConfig $checkoutConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CheckoutSession $checkoutSession,
        private readonly GiftMessageHelper $giftMessageHelper,
        private readonly CheckoutConfig $checkoutConfig
    ) {
    }

    /**
     * Whether an order-level gift message can be added.
     *
     * @return bool
     */
    public function isOrderAllowed(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XPATH_ALLOW_ORDER, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Whether per-item gift messages can be added.
     *
     * @return bool
     */
    public function isItemsAllowed(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XPATH_ALLOW_ITEMS, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Whether the gift-options editor should render at all on the cart.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->isOrderAllowed() || $this->isItemsAllowed();
    }

    /**
     * REST endpoint config the island authenticates with.
     *
     * Reuses the checkout island's restBaseUrl, isLoggedIn and maskedCartId so
     * cart-path/auth resolution stays in one place.
     *
     * @return array{restBaseUrl: string, isLoggedIn: bool, maskedCartId: string}
     */
    public function getEndpointConfig(): array
    {
        $config = $this->checkoutConfig->getConfig();

        return [
            'restBaseUrl' => (string)($config['restBaseUrl'] ?? ''),
            'isLoggedIn' => (bool)($config['isLoggedIn'] ?? false),
            'maskedCartId' => (string)($config['maskedCartId'] ?? ''),
        ];
    }

    /**
     * Already-saved order-level message, or empty strings when none.
     *
     * Lets the island prefill sender/recipient/message on reload.
     *
     * @return array{sender: string, recipient: string, message: string}
     */
    public function getOrderMessage(): array
    {
        try {
            $quote = $this->checkoutSession->getQuote();

            return $this->messageFrom((int)$quote->getGiftMessageId());
        } catch (Throwable) {
            return $this->emptyMessage();
        }
    }

    /**
     * Already-saved message for a cart item id, or empty strings when none.
     *
     * @param int $itemId
     * @return array{sender: string, recipient: string, message: string}
     */
    public function getItemMessage(int $itemId): array
    {
        try {
            $item = $this->checkoutSession->getQuote()->getItemById($itemId);

            return $item ? $this->messageFrom((int)$item->getGiftMessageId()) : $this->emptyMessage();
        } catch (Throwable) {
            return $this->emptyMessage();
        }
    }

    /**
     * Resolve a stored gift-message id into its sender/recipient/message.
     *
     * @param int $messageId
     * @return array{sender: string, recipient: string, message: string}
     */
    private function messageFrom(int $messageId): array
    {
        if ($messageId === 0) {
            return $this->emptyMessage();
        }
        $message = $this->giftMessageHelper->getGiftMessage($messageId);

        return [
            'sender' => (string)$message->getSender(),
            'recipient' => (string)$message->getRecipient(),
            'message' => (string)$message->getMessage(),
        ];
    }

    /**
     * Empty message tuple.
     *
     * @return array{sender: string, recipient: string, message: string}
     */
    private function emptyMessage(): array
    {
        return ['sender' => '', 'recipient' => '', 'message' => ''];
    }
}
