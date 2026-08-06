<?php
declare(strict_types=1);

namespace MageObsidian\Sales\Test\Unit\Block\Order;

use MageObsidian\Sales\Block\Order\History;
use PHPUnit\Framework\TestCase;

/**
 * The subclass exists for one reason: the pager the core block creates in PHP
 * carries Luma's template, which this theme cannot render — and an unrendered
 * pager silently truncates the list at the page size it also applies.
 */
class HistoryTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Magento\Sales\Block\Order\History::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testItReTemplatesTheCoreBlockRatherThanReplacingIt(): void
    {
        $this->assertTrue(
            is_subclass_of(History::class, \Magento\Sales\Block\Order\History::class),
            'The pager must stay the one core builds, so its page size is applied before the load.'
        );
    }

    public function testThePagerTemplateIsTheThemeOne(): void
    {
        $this->assertSame('Magento_Theme::html/pager.twig', History::PAGER_TEMPLATE);
    }
}
