<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Checkout\ViewModel;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Model\Quote\Validator\MinimumOrderAmount\ValidationMessage;
use Throwable;

class CartValidation implements ArgumentInterface
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly ValidationMessage $minimumAmountMessage
    ) {
    }

    public function getMessages(): array
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            $messages = [];
            if (!$quote->validateMinimumAmount()) {
                $messages[] = ['type' => 'notice', 'text' => (string)$this->minimumAmountMessage->getMessage()];
            }
            foreach ($quote->getMessages() as $message) {
                if ($message) {
                    $messages[] = ['type' => (string)$message->getType(), 'text' => (string)$message->getText()];
                }
            }

            return $messages;
        } catch (Throwable) {
            return [];
        }
    }

    public function canProceedToCheckout(): bool
    {
        try {
            $quote = $this->checkoutSession->getQuote();

            return !$quote->getHasError() && $quote->validateMinimumAmount();
        } catch (Throwable) {
            return true;
        }
    }
}
