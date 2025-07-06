<?php
declare(strict_types=1);

namespace MageObsidian\Sales\Test\Unit\Block;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Title as PageTitle;
use MageObsidian\Sales\Block\OrderTitle;
use PHPUnit\Framework\TestCase;

/**
 * The order-detail document title. We assert it is set from the current order's
 * increment id, and left untouched when no order is in the registry.
 */
class OrderTitleTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Context::class) || !function_exists('__')) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    private function buildBlock(PageTitle $title, ?object $order): OrderTitle
    {
        $pageConfig = $this->createMock(PageConfig::class);
        $pageConfig->method('getTitle')->willReturn($title);

        $context = $this->createMock(Context::class);
        $context->method('getPageConfig')->willReturn($pageConfig);

        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->with('current_order')->willReturn($order);

        return new OrderTitle($context, $registry);
    }

    private function invokePrepareLayout(OrderTitle $block): void
    {
        $method = new \ReflectionMethod($block, '_prepareLayout');
        $method->setAccessible(true);
        $method->invoke($block);
    }

    public function testSetsTitleFromCurrentOrder(): void
    {
        $order = $this->getMockBuilder(\stdClass::class)->addMethods(['getRealOrderId'])->getMock();
        $order->method('getRealOrderId')->willReturn('000000123');

        $title = $this->createMock(PageTitle::class);
        $title->expects($this->once())
            ->method('set')
            ->with($this->callback(static fn ($arg): bool => (string)$arg === 'Order # 000000123'));

        $this->invokePrepareLayout($this->buildBlock($title, $order));
    }

    public function testLeavesTitleUntouchedWithoutOrder(): void
    {
        $title = $this->createMock(PageTitle::class);
        $title->expects($this->never())->method('set');

        $this->invokePrepareLayout($this->buildBlock($title, null));
    }
}
