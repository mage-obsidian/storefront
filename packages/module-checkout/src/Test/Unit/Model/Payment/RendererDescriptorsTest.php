<?php
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\Model\Payment;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\LayoutInterface;
use MageObsidian\Checkout\Model\Payment\RendererDescriptors;
use MageObsidian\Checkout\ViewModel\PaymentRenderer;
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

    public function testEachDeclaredMethodCodeGetsItsComponentUrl(): void
    {
        $descriptors = new RendererDescriptors($this->viteResolver());
        $layout = $this->layout([
            'probe.renderer' => $this->blockWith(
                new PaymentRenderer(
                    ['verification_probe', 'verification_probe_vault'],
                    'Verification_PaymentProbe::payment/ProbeMethod'
                )
            ),
        ]);

        $this->assertSame(
            [
                'verification_probe' => [
                    'component' => 'https://shop.test/static/vite/Verification_PaymentProbe/components/payment/ProbeMethod.js',
                ],
                'verification_probe_vault' => [
                    'component' => 'https://shop.test/static/vite/Verification_PaymentProbe/components/payment/ProbeMethod.js',
                ],
            ],
            $descriptors->collect($layout)
        );
    }

    public function testABlockDroppedByIfconfigContributesNothing(): void
    {
        $descriptors = new RendererDescriptors($this->viteResolver());
        $layout = $this->createStub(LayoutInterface::class);
        $layout->method('getChildNames')->willReturn(['probe.renderer']);
        $layout->method('getBlock')->willReturn(false);

        $this->assertSame([], $descriptors->collect($layout));
    }

    public function testABlockWithoutARendererArgumentContributesNothing(): void
    {
        $descriptors = new RendererDescriptors($this->viteResolver());
        $layout = $this->layout(['plain.block' => $this->blockWith(null)]);

        $this->assertSame([], $descriptors->collect($layout));
    }

    public function testARelativeReferenceIsRefusedInsteadOfResolved(): void
    {
        $resolver = $this->createMock(ViteResolver::class);
        $resolver->expects($this->never())->method('resolveComponentPath');

        $descriptors = new RendererDescriptors($resolver);
        $layout = $this->layout([
            'probe.renderer' => $this->blockWith(new PaymentRenderer(['verification_probe'], 'payment/ProbeMethod')),
        ]);

        $this->assertSame([], $descriptors->collect($layout));
    }

    public function testOneBrokenDeclarationDoesNotHideTheRest(): void
    {
        $descriptors = new RendererDescriptors($this->viteResolver());
        $layout = $this->layout([
            'broken.renderer' => $this->blockWith(new PaymentRenderer([], 'Vendor_Module::payment/Broken')),
            'probe.renderer' => $this->blockWith(
                new PaymentRenderer(['verification_probe'], 'Verification_PaymentProbe::payment/ProbeMethod')
            ),
        ]);

        $this->assertSame(['verification_probe'], array_keys($descriptors->collect($layout)));
    }

    public function testAMethodSpecificRendererBeatsTheOneDeclaredAfterIt(): void
    {
        $descriptors = new RendererDescriptors($this->viteResolver());
        $layout = $this->layout([
            'gateway.saved_cards' => $this->blockWith(
                new PaymentRenderer(['acme_cc_vault'], 'Acme_Gateway::payment/AcmeCards')
            ),
            'obsidian.vault.saved_cards' => $this->blockWith(
                new PaymentRenderer(['acme_cc_vault', 'other_cc_vault'], 'MageObsidian_Vault::payment/SavedCards')
            ),
        ]);

        $collected = $descriptors->collect($layout);

        $this->assertStringContainsString('Acme_Gateway', $collected['acme_cc_vault']['component']);
        $this->assertStringContainsString('MageObsidian_Vault', $collected['other_cc_vault']['component']);
    }
}
