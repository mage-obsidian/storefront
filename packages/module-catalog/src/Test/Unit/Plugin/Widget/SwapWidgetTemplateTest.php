<?php
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Catalog\Test\Unit\Plugin\Widget;

use Magento\Framework\View\Element\Template;
use MageObsidian\Catalog\Plugin\Widget\SwapWidgetTemplate;
use MageObsidian\Catalog\ViewModel\ProductCard;
use MageObsidian\Catalog\ViewModel\RecentlyProducts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SwapWidgetTemplateTest extends TestCase
{
    private ProductCard $card;

    private RecentlyProducts $recently;

    protected function setUp(): void
    {
        if (!class_exists(Template::class)) {
            $this->markTestSkipped('Magento Framework is not available in this runtime.');
        }
        $this->card = $this->createStub(ProductCard::class);
        $this->recently = $this->createStub(RecentlyProducts::class);
    }

    private function block(string $template, array $data = []): Template
    {
        $block = new class extends Template {
            public function __construct()
            {
            }
        };
        $block->setData($data);
        $block->setTemplate($template);

        return $block;
    }

    private function plugin(): SwapWidgetTemplate
    {
        return new SwapWidgetTemplate($this->card, $this->recently);
    }

    public static function swapped(): array
    {
        return [
            'new grid' => ['product/widget/new/content/new_grid.phtml', SwapWidgetTemplate::NEW_GRID],
            'new list' => ['product/widget/new/content/new_list.phtml', SwapWidgetTemplate::NEW_GRID],
            'new column' => ['product/widget/new/column/new_names_list.phtml', SwapWidgetTemplate::NEW_COLUMN],
            'viewed grid' => ['product/widget/viewed/grid.phtml', SwapWidgetTemplate::RECENT],
            'compared sidebar' => ['product/widget/compared/sidebar.phtml', SwapWidgetTemplate::RECENT],
        ];
    }

    #[DataProvider('swapped')]
    public function testTheCoreTemplateIsSwappedForTheThemeOne(string $core, string $expected): void
    {
        $block = $this->block($core);

        $this->plugin()->beforeToHtml($block);

        $this->assertSame($expected, $block->getTemplate());
    }

    public function testAModulePrefixOnTheStoredTemplateDoesNotStopTheSwap(): void
    {
        $block = $this->block('Magento_Catalog::product/widget/new/content/new_grid.phtml');

        $this->plugin()->beforeToHtml($block);

        $this->assertSame(SwapWidgetTemplate::NEW_GRID, $block->getTemplate());
    }

    public function testATemplateTheMerchantChoseIsLeftAlone(): void
    {
        $block = $this->block('Vendor_Module::custom/new.phtml');

        $this->plugin()->beforeToHtml($block);

        $this->assertSame('Vendor_Module::custom/new.phtml', $block->getTemplate());
        $this->assertNull($block->getData('card'));
    }

    public function testTheCardAndTheRecentlyViewModelAreHandedOver(): void
    {
        $block = $this->block('product/widget/new/content/new_grid.phtml');

        $this->plugin()->beforeToHtml($block);

        $this->assertSame($this->card, $block->getData('card'));
        $this->assertSame($this->recently, $block->getData('recently'));
    }

    public function testACardAlreadyOnTheBlockIsKept(): void
    {
        $other = $this->createStub(ProductCard::class);
        $block = $this->block('product/widget/new/content/new_grid.phtml', ['card' => $other]);

        $this->plugin()->beforeToHtml($block);

        $this->assertSame($other, $block->getData('card'));
    }

    public function testTheKindOfARecentWidgetIsRecorded(): void
    {
        $block = $this->block('product/widget/compared/sidebar.phtml');

        $this->plugin()->beforeToHtml($block);

        $this->assertSame('compared', $block->getData('recent_kind'));
    }

    public function testTheVariantOfANewProductsColumnIsRecorded(): void
    {
        $block = $this->block('product/widget/new/column/new_images_list.phtml');

        $this->plugin()->beforeToHtml($block);

        $this->assertSame('images', $block->getData('column_variant'));
    }
}
