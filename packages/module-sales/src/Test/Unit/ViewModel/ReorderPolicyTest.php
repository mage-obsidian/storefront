<?php
/**
 * This file is part of the MageObsidian - Sales project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Sales\Test\Unit\ViewModel;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Helper\Reorder;
use MageObsidian\Sales\ViewModel\ReorderPolicy;
use PHPUnit\Framework\TestCase;

class ReorderPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Reorder::class)) {
            $this->markTestSkipped('Magento Sales is not available in this runtime.');
        }
    }

    public function testAsksTheCoreWhetherTheOrderCanBeOrderedAgain(): void
    {
        $helper = $this->createMock(Reorder::class);
        $helper->expects($this->once())->method('canReorder')->with(42)->willReturn(true);

        $this->assertTrue((new ReorderPolicy($helper))->canReorder($this->orderWithId(42)));
    }

    public function testFollowsTheCoreWhenItRefuses(): void
    {
        $helper = $this->createMock(Reorder::class);
        $helper->method('canReorder')->willReturn(false);

        $this->assertFalse((new ReorderPolicy($helper))->canReorder($this->orderWithId(42)));
    }

    public function testRefusesWithoutAnOrder(): void
    {
        $helper = $this->createMock(Reorder::class);
        $helper->expects($this->never())->method('canReorder');

        $this->assertFalse((new ReorderPolicy($helper))->canReorder(null));
    }

    public function testRefusesAnOrderThatWasNeverSaved(): void
    {
        $helper = $this->createMock(Reorder::class);
        $helper->expects($this->never())->method('canReorder');

        $this->assertFalse((new ReorderPolicy($helper))->canReorder($this->orderWithId(0)));
    }

    private function orderWithId(int $id): OrderInterface
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn($id);

        return $order;
    }
}
