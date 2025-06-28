<?php
/**
 * This file is part of the MageObsidian - GiftMessage project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\GiftMessage\ViewModel;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\GiftMessage\Helper\Message as GiftMessageHelper;
use Throwable;

/**
 * Gift-message display for the customer order view (a MageObsidian enhancement —
 * native Luma shows the message only in admin and the cart).
 *
 * Reads the saved message off the order and its items by the stored
 * gift_message_id, so the order-view Twig can show "From / To / Message" for the
 * whole order and per line. Injected as an optional argument from this module's
 * layout, keeping Magento_Sales unaware of gift messages.
 */
class OrderGiftMessage implements ArgumentInterface
{
    /**
     * @param GiftMessageHelper $giftMessageHelper
     */
    public function __construct(
        private readonly GiftMessageHelper $giftMessageHelper
    ) {
    }

    /**
     * Order-level message, or empty strings when none.
     *
     * @param DataObject $order
     * @return array{sender: string, recipient: string, message: string}
     */
    public function getOrderMessage(DataObject $order): array
    {
        return $this->messageFrom((int)$order->getGiftMessageId());
    }

    /**
     * Per-item message, or empty strings when none.
     *
     * @param DataObject $item
     * @return array{sender: string, recipient: string, message: string}
     */
    public function getItemMessage(DataObject $item): array
    {
        return $this->messageFrom((int)$item->getGiftMessageId());
    }

    /**
     * Whether the order carries an order-level gift message worth rendering.
     *
     * @param DataObject $order
     * @return bool
     */
    public function hasOrderMessage(DataObject $order): bool
    {
        return $this->getOrderMessage($order)['message'] !== '';
    }

    /**
     * Resolve a stored gift-message id into sender/recipient/message.
     *
     * @param int $messageId
     * @return array{sender: string, recipient: string, message: string}
     */
    private function messageFrom(int $messageId): array
    {
        if ($messageId === 0) {
            return ['sender' => '', 'recipient' => '', 'message' => ''];
        }

        try {
            $message = $this->giftMessageHelper->getGiftMessage($messageId);

            return [
                'sender' => (string)$message->getSender(),
                'recipient' => (string)$message->getRecipient(),
                'message' => (string)$message->getMessage(),
            ];
        } catch (Throwable) {
            return ['sender' => '', 'recipient' => '', 'message' => ''];
        }
    }
}
