<?php
declare(strict_types=1);

namespace MageObsidian\GiftMessage\Test\Unit\ViewModel;

use Magento\Framework\DataObject;
use Magento\GiftMessage\Helper\Message as GiftMessageHelper;
use MageObsidian\GiftMessage\ViewModel\OrderGiftMessage;
use PHPUnit\Framework\TestCase;

/**
 * Gift-message display VM for the order view. We assert it resolves the stored
 * gift_message_id into sender/recipient/message and degrades to empty strings
 * when there is no message.
 */
class OrderGiftMessageTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(GiftMessageHelper::class)) {
            $this->markTestSkipped('Magento GiftMessage is not available in this runtime.');
        }
    }

    private function helperReturning(?DataObject $message): GiftMessageHelper
    {
        $helper = $this->createMock(GiftMessageHelper::class);
        $helper->method('getGiftMessage')->willReturn($message ?? new DataObject());

        return $helper;
    }

    public function testOrderMessageResolvesTheStoredId(): void
    {
        $message = new DataObject(['sender' => 'Jean', 'recipient' => 'Alex', 'message' => 'Enjoy']);
        $view = new OrderGiftMessage($this->helperReturning($message));

        $order = new DataObject(['gift_message_id' => 5]);

        $this->assertSame(
            ['sender' => 'Jean', 'recipient' => 'Alex', 'message' => 'Enjoy'],
            $view->getOrderMessage($order)
        );
        $this->assertTrue($view->hasOrderMessage($order));
    }

    public function testEmptyWhenNoMessageId(): void
    {
        $view = new OrderGiftMessage($this->helperReturning(new DataObject()));
        $order = new DataObject(['gift_message_id' => 0]);

        $this->assertSame(['sender' => '', 'recipient' => '', 'message' => ''], $view->getOrderMessage($order));
        $this->assertFalse($view->hasOrderMessage($order));
    }

    public function testItemMessageResolvesTheStoredId(): void
    {
        $message = new DataObject(['sender' => 'Jean', 'recipient' => 'Mom', 'message' => 'For you']);
        $view = new OrderGiftMessage($this->helperReturning($message));

        $item = new DataObject(['gift_message_id' => 6]);

        $this->assertSame(
            ['sender' => 'Jean', 'recipient' => 'Mom', 'message' => 'For you'],
            $view->getItemMessage($item)
        );
    }
}
