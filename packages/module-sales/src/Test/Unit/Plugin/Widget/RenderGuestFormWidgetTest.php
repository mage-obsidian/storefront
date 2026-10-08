<?php
/**
 * This file is part of the MageObsidian - Sales project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Sales\Test\Unit\Plugin\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;
use MageObsidian\Sales\Plugin\Widget\RenderGuestFormWidget;
use MageObsidian\Sales\ViewModel\OrdersReturnsForm;
use PHPUnit\Framework\TestCase;

class RenderGuestFormWidgetTest extends TestCase
{
    private OrdersReturnsForm $viewModel;

    private array $created = [];

    protected function setUp(): void
    {
        if (!class_exists(Template::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
        $this->viewModel = $this->createStub(OrdersReturnsForm::class);
        $this->created = [];
    }

    private function block(string $template, array $data = []): Template
    {
        $layout = $this->createStub(LayoutInterface::class);
        $layout->method('createBlock')->willReturnCallback(function (string $type, string $name, array $arguments): Template {
            $this->created[] = [$type, $arguments];

            return $this->createStub(Template::class);
        });

        $block = new class ($layout) extends Template {
            public function __construct(private readonly LayoutInterface $stubLayout)
            {
            }

            public function getLayout(): LayoutInterface
            {
                return $this->stubLayout;
            }
        };
        $block->setData($data);
        $block->setTemplate($template);

        return $block;
    }

    public function testTheCoreTemplateIsSwappedForTheIslandOne(): void
    {
        $block = $this->block('widget/guest/form.phtml');

        (new RenderGuestFormWidget($this->viewModel))->beforeToHtml($block);

        $this->assertSame(RenderGuestFormWidget::OBSIDIAN_TEMPLATE, $block->getTemplate());
        $this->assertCount(1, $this->created);
        $this->assertSame(
            ['template' => RenderGuestFormWidget::ISLAND_TEMPLATE, 'view' => $this->viewModel],
            $this->created[0][1]['data']
        );
        $this->assertInstanceOf(Template::class, $block->getData('island'));
    }

    public function testAModulePrefixOnTheStoredTemplateDoesNotStopTheSwap(): void
    {
        $block = $this->block('Magento_Sales::widget/guest/form.phtml');

        (new RenderGuestFormWidget($this->viewModel))->beforeToHtml($block);

        $this->assertSame(RenderGuestFormWidget::OBSIDIAN_TEMPLATE, $block->getTemplate());
    }

    public function testATemplateTheMerchantChoseIsLeftAlone(): void
    {
        $block = $this->block('Vendor_Module::my/form.phtml');

        (new RenderGuestFormWidget($this->viewModel))->beforeToHtml($block);

        $this->assertSame('Vendor_Module::my/form.phtml', $block->getTemplate());
        $this->assertNull($block->getData('island'));
        $this->assertSame([], $this->created);
    }

    public function testAnIslandAlreadyOnTheBlockIsKept(): void
    {
        $other = $this->createStub(Template::class);
        $block = $this->block('widget/guest/form.phtml', ['island' => $other]);

        (new RenderGuestFormWidget($this->viewModel))->beforeToHtml($block);

        $this->assertSame($other, $block->getData('island'));
        $this->assertSame([], $this->created);
    }
}
