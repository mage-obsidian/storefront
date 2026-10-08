<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\Model\Shipping;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\LayoutInterface;
use MageObsidian\Checkout\Model\Shipping\RendererDescriptors;
use MageObsidian\Checkout\ViewModel\ShippingRenderer;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use PHPUnit\Framework\TestCase;

class RendererDescriptorsTest extends TestCase
{
    private function viteResolver(): ViteResolver
    {
        $resolver = $this->createStub(ViteResolver::class);
        $resolver->method('resolveComponentPath')->willReturnCallback(
            static fn (string $name): string => 'https://shop.test/static/vite/'
                . str_replace('::', '/components/', $name) . '.js'
        );

        return $resolver;
    }

    private function blockWith(mixed $renderer): AbstractBlock
    {
        $block = $this->createStub(AbstractBlock::class);
        $block->method('getData')->willReturnCallback(
            static fn (string $key = '') => $key === RendererDescriptors::ARGUMENT ? $renderer : null
        );

        return $block;
    }

    private function layout(array $blocksByName): LayoutInterface
    {
        $layout = $this->createStub(LayoutInterface::class);
        $layout->method('getChildNames')->willReturn(array_keys($blocksByName));
        $layout->method('getBlock')->willReturnCallback(
            static fn (string $name) => $blocksByName[$name] ?? false
        );

        return $layout;
    }

    public function testEachDeclaredCarrierGetsItsComponentUrlAndConfig(): void
    {
        $descriptors = new RendererDescriptors($this->viteResolver());
        $layout = $this->layout([
            'pickup.renderer' => $this->blockWith(
                new ShippingRenderer(['instore'], 'Acme_Pickup::shipping/Finder', ['radius' => 200])
            ),
        ]);

        $this->assertSame(
            [
                'instore' => [
                    'component' => 'https://shop.test/static/vite/Acme_Pickup/components/shipping/Finder.js',
                    'config' => ['radius' => 200],
                ],
            ],
            $descriptors->collect($layout)
        );
    }

    public function testABlockDroppedByIfconfigContributesNothing(): void
    {
        $descriptors = new RendererDescriptors($this->viteResolver());
        $layout = $this->createStub(LayoutInterface::class);
        $layout->method('getChildNames')->willReturn(['pickup.renderer']);
        $layout->method('getBlock')->willReturn(false);

        $this->assertSame([], $descriptors->collect($layout));
    }

    public function testABlockWithoutARendererArgumentContributesNothing(): void
    {
        $descriptors = new RendererDescriptors($this->viteResolver());

        $this->assertSame([], $descriptors->collect($this->layout(['plain.block' => $this->blockWith(null)])));
    }

    public function testARelativeReferenceIsRefusedInsteadOfResolved(): void
    {
        $resolver = $this->createMock(ViteResolver::class);
        $resolver->expects($this->never())->method('resolveComponentPath');

        $layout = $this->layout([
            'pickup.renderer' => $this->blockWith(new ShippingRenderer(['instore'], 'shipping/Finder')),
        ]);

        $this->assertSame([], (new RendererDescriptors($resolver))->collect($layout));
    }

    public function testOneBrokenDeclarationDoesNotHideTheRest(): void
    {
        $layout = $this->layout([
            'broken.renderer' => $this->blockWith(new ShippingRenderer([], 'Acme_Pickup::shipping/Broken')),
            'pickup.renderer' => $this->blockWith(new ShippingRenderer(['instore'], 'Acme_Pickup::shipping/Finder')),
        ]);

        $this->assertSame(['instore'], array_keys((new RendererDescriptors($this->viteResolver()))->collect($layout)));
    }

    public function testTheFirstRendererDeclaredForACarrierWins(): void
    {
        $layout = $this->layout([
            'first' => $this->blockWith(new ShippingRenderer(['instore'], 'Acme_Pickup::shipping/First')),
            'second' => $this->blockWith(new ShippingRenderer(['instore'], 'Other_Pickup::shipping/Second')),
        ]);

        $collected = (new RendererDescriptors($this->viteResolver()))->collect($layout);

        $this->assertStringContainsString('Acme_Pickup', $collected['instore']['component']);
    }
}
