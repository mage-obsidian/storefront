<?php
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Catalog\Test\Unit\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Swatches\Block\Product\Renderer\Listing\Configurable;
use MageObsidian\Catalog\Service\Product\ConfigurableInitialState;
use MageObsidian\Catalog\Service\Product\ListingSwatchState;
use MageObsidian\Catalog\ViewModel\ListingSwatches;
use MageObsidian\Catalog\ViewModel\ProductCard;
use PHPUnit\Framework\TestCase;

class ListingSwatchesTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Configurable::class) || !class_exists(Product::class)) {
            $this->markTestSkipped('Magento Swatches is not available in this runtime.');
        }
    }

    private function config(): string
    {
        return (string)json_encode([
            'attributes' => ['93' => [
                'id' => '93',
                'code' => 'color',
                'label' => 'Color',
                'position' => '0',
                'options' => [['id' => '50', 'label' => 'Blue']],
            ]],
            'index' => ['101' => ['93' => '50']],
        ]);
    }

    private function swatches(): string
    {
        return (string)json_encode(['93' => ['50' => ['type' => '1', 'value' => '#1f4ed8', 'label' => 'Blue']]]);
    }

    private function variant(?string $smallImage): Product
    {
        $variant = $this->createMock(Product::class);
        $variant->method('getId')->willReturn(101);
        $variant->method('getData')->with('small_image')->willReturn($smallImage);

        return $variant;
    }

    private function renderer(Product $variant, string $limit = '16', ?string $config = null): Configurable
    {
        $type = $this->createMock(ConfigurableType::class);
        $type->method('getUsedProducts')->willReturn([$variant]);

        $parent = $this->createMock(Product::class);
        $parent->method('getTypeInstance')->willReturn($type);

        $renderer = $this->createMock(Configurable::class);
        $renderer->method('getProduct')->willReturn($parent);
        $renderer->method('getJsonConfig')->willReturn($config ?? $this->config());
        $renderer->method('getJsonSwatchConfig')->willReturn($this->swatches());
        $renderer->method('getNumberSwatchesPerProduct')->willReturn($limit);

        return $renderer;
    }

    private function viewModel(?ProductCard $card = null, array $flags = []): ListingSwatches
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn(string $path): bool => $flags[$path] ?? false
        );

        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->method('format')->willReturnCallback(
            static fn(float $amount): string => sprintf('$%.2f', $amount)
        );

        return new ListingSwatches(
            new ListingSwatchState(new ConfigurableInitialState()),
            $card ?? $this->createMock(ProductCard::class),
            $scopeConfig,
            $priceCurrency
        );
    }

    public function testAVariantWithAnImageGivesItsOptionTheCardImage(): void
    {
        $card = $this->createMock(ProductCard::class);
        $card->method('getImage')->willReturn(
            ['src' => '/m/blue.jpg', 'srcset' => '/m/blue.jpg 240w', 'width' => 240, 'height' => 300, 'label' => '']
        );

        $groups = $this->viewModel($card)->groups($this->renderer($this->variant('/b/blue.jpg')));

        $this->assertSame(
            ['src' => '/m/blue.jpg', 'srcset' => '/m/blue.jpg 240w'],
            $groups[0]['options'][0]['image']
        );
    }

    public function testAVariantWithoutAFileGivesItsOptionNoImage(): void
    {
        foreach ([null, '', 'no_selection'] as $file) {
            $groups = $this->viewModel()->groups($this->renderer($this->variant($file)));

            $this->assertNull($groups[0]['options'][0]['image']);
        }
    }

    public function testTheViewCarriesThePurchaseMapFormattedWithTheStoreCurrency(): void
    {
        $card = $this->createMock(ProductCard::class);
        $card->method('getAddToCartPostParams')->willReturn(['action' => '/cart/add', 'data' => ['product' => 7, 'uenc' => 'abc']]);
        $renderer = $this->renderer($this->variant(null), '16', (string)json_encode([
            'attributes' => ['93' => [
                'id' => '93',
                'code' => 'color',
                'label' => 'Color',
                'position' => '0',
                'options' => [['id' => '50', 'label' => 'Blue']],
            ]],
            'index' => ['101' => ['93' => '50']],
            'optionPrices' => ['101' => ['finalPrice' => ['amount' => 59]]],
        ]));

        $view = $this->viewModel($card)->view($renderer);

        $this->assertSame(['50' => ['price' => '$59.00', 'id' => 101]], $view['purchase']['variants']);
        $this->assertSame('/cart/add', $view['post']['action']);
    }

    public function testABrokenRendererDegradesToNoSwatches(): void
    {
        $renderer = $this->createMock(Configurable::class);
        $renderer->method('getProduct')->willThrowException(new \RuntimeException('boom'));

        $this->assertSame([], $this->viewModel()->groups($renderer));
    }

    public function testTheSettingsAreRead(): void
    {
        $viewModel = $this->viewModel(null, [
            'catalog/frontend/show_swatches_in_product_list' => true,
            'catalog/frontend/show_swatch_tooltip' => false,
        ]);

        $this->assertTrue($viewModel->isEnabled());
        $this->assertFalse($viewModel->showsTooltip());
    }
}
