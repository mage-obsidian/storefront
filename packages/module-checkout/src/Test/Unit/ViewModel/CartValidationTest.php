<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\ViewModel;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\MessageInterface;
use Magento\Framework\Phrase;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Validator\MinimumOrderAmount\ValidationMessage;
use MageObsidian\Checkout\ViewModel\CartValidation;
use PHPUnit\Framework\TestCase;

class CartValidationTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Quote::class)) {
            $this->markTestSkipped('Magento Quote is not available in this runtime.');
        }
    }

    public function testStatesNothingForACartThatMeetsEveryRule(): void
    {
        $viewModel = $this->viewModel($this->quote(true, false, []));

        $this->assertSame([], $viewModel->getMessages());
        $this->assertTrue($viewModel->canProceedToCheckout());
    }

    public function testAnnouncesTheMinimumAmountAndBlocksCheckout(): void
    {
        $viewModel = $this->viewModel($this->quote(false, false, []), 'Spend at least $50.');

        $this->assertSame(
            [['type' => 'notice', 'text' => 'Spend at least $50.']],
            $viewModel->getMessages()
        );
        $this->assertFalse($viewModel->canProceedToCheckout());
    }

    public function testListsTheQuoteMessagesWithTheirType(): void
    {
        $quote = $this->quote(true, false, [
            $this->message('error', 'The requested qty is not available'),
            $this->message('notice', 'The price changed'),
        ]);

        $this->assertSame(
            [
                ['type' => 'error', 'text' => 'The requested qty is not available'],
                ['type' => 'notice', 'text' => 'The price changed'],
            ],
            $this->viewModel($quote)->getMessages()
        );
    }

    public function testBlocksCheckoutWhenTheQuoteHasAnError(): void
    {
        $this->assertFalse($this->viewModel($this->quote(true, true, []))->canProceedToCheckout());
    }

    public function testListsTheMinimumAmountBeforeTheQuoteMessages(): void
    {
        $quote = $this->quote(false, false, [$this->message('error', 'Out of stock')]);

        $texts = array_column($this->viewModel($quote, 'Spend more')->getMessages(), 'text');

        $this->assertSame(['Spend more', 'Out of stock'], $texts);
    }

    public function testDegradesToAnOpenCartWhenTheQuoteCannotBeRead(): void
    {
        $session = $this->createMock(CheckoutSession::class);
        $session->method('getQuote')->willThrowException(new \RuntimeException('no session'));

        $viewModel = new CartValidation($session, $this->createMock(ValidationMessage::class));

        $this->assertSame([], $viewModel->getMessages());
        $this->assertTrue($viewModel->canProceedToCheckout());
    }

    private function message(string $type, string $text): MessageInterface
    {
        $message = $this->createMock(MessageInterface::class);
        $message->method('getType')->willReturn($type);
        $message->method('getText')->willReturn($text);

        return $message;
    }

    private function quote(bool $meetsMinimum, bool $hasError, array $messages): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['validateMinimumAmount', 'getMessages'])
            ->getMock();
        $quote->method('validateMinimumAmount')->willReturn($meetsMinimum);
        $quote->setData('has_error', $hasError);
        $quote->method('getMessages')->willReturn($messages);

        return $quote;
    }

    private function viewModel(Quote $quote, string $minimumNotice = ''): CartValidation
    {
        $session = $this->createMock(CheckoutSession::class);
        $session->method('getQuote')->willReturn($quote);
        $minimum = $this->createMock(ValidationMessage::class);
        $minimum->method('getMessage')->willReturn(new Phrase($minimumNotice));

        return new CartValidation($session, $minimum);
    }
}
