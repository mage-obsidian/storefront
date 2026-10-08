<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\ViewModel;

use InvalidArgumentException;
use MageObsidian\Checkout\ViewModel\ShippingRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ShippingRendererTest extends TestCase
{
    public function testItCarriesTheCarrierCodesAndTheConfigItWasDeclaredWith(): void
    {
        $renderer = new ShippingRenderer(
            ['instore', ' acme '],
            'Acme_Pickup::shipping/Finder',
            ['radius' => 200]
        );

        $this->assertSame(['instore', 'acme'], $renderer->getCarrierCodes());
        $this->assertSame('Acme_Pickup::shipping/Finder', $renderer->getComponent());
        $this->assertSame(['radius' => 200], $renderer->getConfig());
    }

    public function testADeclarationWithoutCarrierCodesIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ShippingRenderer(['', '  '], 'Acme_Pickup::shipping/Finder'))->getCarrierCodes();
    }

    public function testADeclarationWithoutAComponentIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ShippingRenderer(['instore'], ''))->getComponent();
    }

    #[DataProvider('relativeReferences')]
    public function testARelativeComponentReferenceIsRejected(string $component): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ShippingRenderer(['instore'], $component))->getComponent();
    }

    public static function relativeReferences(): array
    {
        return [
            'bare path' => ['shipping/Finder'],
            'dot slash' => ['./Finder'],
            'parent' => ['../shipping/Finder'],
            'escaping a qualified prefix' => ['Acme_Pickup::../Finder'],
            'absolute' => ['/shipping/Finder'],
        ];
    }
}
