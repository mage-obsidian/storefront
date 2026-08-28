<?php
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\Block;

use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\LayoutInterface;
use MageObsidian\Checkout\Block\Checkout;
use MageObsidian\Checkout\Model\Payment\RendererDescriptors;
use MageObsidian\Checkout\Model\Payment\Withdrawn;
use MageObsidian\ModernFrontend\Service\Vue\IslandMarkup;
use MageObsidian\ModernFrontend\ViewModel\Image;
use MageObsidian\ModernFrontend\ViewModel\SchemaOrg;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CheckoutTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Context::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    private function block(RendererDescriptors $descriptors, ?Withdrawn $withdrawn = null): Checkout
    {
        $block = new Checkout(
            $this->createStub(Context::class),
            $this->createStub(ViteResolver::class),
            $this->createStub(SchemaOrg::class),
            $this->createStub(Image::class),
            $this->createStub(IslandMarkup::class),
            $descriptors,
            $withdrawn ?? $this->createStub(Withdrawn::class)
        );
        $block->setLayout($this->createStub(LayoutInterface::class));

        return $block;
    }

    public function testItHandsTheCollectedDescriptorsToTheIsland(): void
    {
        $collected = ['verification_probe' => ['component' => 'https://shop.test/probe.js']];
        $descriptors = $this->createStub(RendererDescriptors::class);
        $descriptors->method('collect')->willReturn($collected);

        $this->assertSame($collected, $this->block($descriptors)->getPaymentRenderers());
    }

    public function testTheCollectionIsAskedForOnlyOnce(): void
    {
        $descriptors = $this->createMock(RendererDescriptors::class);
        $descriptors->expects($this->once())->method('collect')->willReturn([]);

        $block = $this->block($descriptors);
        $block->getPaymentRenderers();
        $block->getPaymentRenderers();
    }

    public function testACollectionThatFailsLeavesTheCheckoutWithoutRenderers(): void
    {
        $descriptors = $this->createStub(RendererDescriptors::class);
        $descriptors->method('collect')->willThrowException(new RuntimeException('boom'));

        $this->assertSame([], $this->block($descriptors)->getPaymentRenderers());
    }

    public function testItHandsTheIslandTheMethodsTheCheckoutWithdrew(): void
    {
        $descriptors = $this->createStub(RendererDescriptors::class);
        $descriptors->method('collect')->willReturn(['acme_card' => ['component' => 'https://shop.test/acme.js']]);

        $withdrawn = $this->createMock(Withdrawn::class);
        $withdrawn->expects($this->once())
            ->method('codes')
            ->with(['acme_card'])
            ->willReturn(['braintree']);

        $this->assertSame(['braintree'], $this->block($descriptors, $withdrawn)->getWithdrawnMethods());
    }

    public function testAWithdrawalThatFailsLeavesEveryMethodOffered(): void
    {
        $withdrawn = $this->createStub(Withdrawn::class);
        $withdrawn->method('codes')->willThrowException(new RuntimeException('boom'));

        $this->assertSame([], $this->block($this->createStub(RendererDescriptors::class), $withdrawn)->getWithdrawnMethods());
    }
}
