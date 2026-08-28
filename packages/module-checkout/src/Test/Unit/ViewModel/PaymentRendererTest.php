<?php
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\ViewModel;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use MageObsidian\Checkout\ViewModel\PaymentRenderer;
use PHPUnit\Framework\TestCase;

class PaymentRendererTest extends TestCase
{
    public function testItCarriesTheMethodCodesItWasDeclaredWith(): void
    {
        $renderer = new PaymentRenderer(
            ['verification_probe', ' verification_probe_vault '],
            'Verification_PaymentProbe::payment/ProbeMethod'
        );

        $this->assertSame(['verification_probe', 'verification_probe_vault'], $renderer->getMethodCodes());
        $this->assertSame('Verification_PaymentProbe::payment/ProbeMethod', $renderer->getComponent());
    }

    public function testADeclarationWithoutMethodCodesIsRejected(): void
    {
        $renderer = new PaymentRenderer([], 'Verification_PaymentProbe::payment/ProbeMethod');

        $this->expectException(InvalidArgumentException::class);
        $renderer->getMethodCodes();
    }

    public function testADeclarationWithBlankMethodCodesIsRejected(): void
    {
        $renderer = new PaymentRenderer(['', '  '], 'Verification_PaymentProbe::payment/ProbeMethod');

        $this->expectException(InvalidArgumentException::class);
        $renderer->getMethodCodes();
    }

    public function testADeclarationWithoutAComponentIsRejected(): void
    {
        $renderer = new PaymentRenderer(['verification_probe'], '');

        $this->expectException(InvalidArgumentException::class);
        $renderer->getComponent();
    }

    #[DataProvider('relativeReferences')]
    public function testARelativeComponentReferenceIsRejected(string $component): void
    {
        $renderer = new PaymentRenderer(['verification_probe'], $component);

        $this->expectException(InvalidArgumentException::class);
        $renderer->getComponent();
    }

    public static function relativeReferences(): array
    {
        return [
            'bare path' => ['payment/ProbeMethod'],
            'dot slash' => ['./ProbeMethod'],
            'parent' => ['../payment/ProbeMethod'],
            'escaping a qualified prefix' => ['Verification_PaymentProbe::../ProbeMethod'],
            'absolute' => ['/payment/ProbeMethod'],
        ];
    }
}
