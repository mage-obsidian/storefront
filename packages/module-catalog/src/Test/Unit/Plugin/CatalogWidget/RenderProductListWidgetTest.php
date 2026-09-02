<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Catalog\Test\Unit\Plugin\CatalogWidget;

use Magento\CatalogWidget\Block\Product\ProductsList;
use MageObsidian\Catalog\Plugin\CatalogWidget\RenderProductListWidget;
use MageObsidian\Catalog\ViewModel\ProductCard;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class RenderProductListWidgetTest extends TestCase
{
    /**
     * A widget instance stores the template it was placed with, so a widget
     * already on a page would keep rendering a Luma grid this theme has no
     * styles for.
     */
    public function testSwapsThePlatformsOwnTemplateForTheThemes(): void
    {
        $widget = $this->widget(RenderProductListWidget::CORE_TEMPLATE);

        $this->plugin()->beforeToHtml($widget);

        $this->assertSame(RenderProductListWidget::OBSIDIAN_TEMPLATE, $widget->getTemplate());
    }

    // A merchant who picked a template of their own keeps it.
    public function testLeavesATemplateSomebodyChoseAlone(): void
    {
        $widget = $this->widget('Vendor_Module::their/own/grid.phtml');

        $this->plugin()->beforeToHtml($widget);

        $this->assertSame('Vendor_Module::their/own/grid.phtml', $widget->getTemplate());
    }

    public function testTakesOverAWidgetThatWasPlacedWithNoTemplateAtAll(): void
    {
        $widget = $this->widget('');

        $this->plugin()->beforeToHtml($widget);

        $this->assertSame(RenderProductListWidget::OBSIDIAN_TEMPLATE, $widget->getTemplate());
    }

    /**
     * The card partial reads its image and price helpers off the block. A layout
     * supplies them on the category listing; a widget block is built by the
     * widget system and has no layout to supply anything.
     */
    public function testHandsTheCardOverWithTheTemplate(): void
    {
        $widget = $this->widget(RenderProductListWidget::CORE_TEMPLATE);
        $card = $this->createMock(ProductCard::class);

        (new RenderProductListWidget($card))->beforeToHtml($widget);

        $this->assertSame($card, $widget->getData('card'));
    }

    public function testDoesNotDisplaceACardSomethingElseAlreadySet(): void
    {
        $widget = $this->widget(RenderProductListWidget::CORE_TEMPLATE);
        $theirs = $this->createMock(ProductCard::class);
        $widget->setData('card', $theirs);

        $this->plugin()->beforeToHtml($widget);

        $this->assertSame($theirs, $widget->getData('card'));
    }

    /**
     * Page Builder's carousel appearance pins a template that expects slick, so
     * without this the carousel is the one appearance that keeps Luma's markup.
     */
    public function testSwapsThePageBuilderCarouselTemplateToo(): void
    {
        $widget = $this->widget(RenderProductListWidget::CORE_CAROUSEL_TEMPLATE);

        $this->plugin()->beforeToHtml($widget);

        $this->assertSame(RenderProductListWidget::OBSIDIAN_CAROUSEL_TEMPLATE, $widget->getTemplate());
    }

    public function testKeepsTheTwoAppearancesApart(): void
    {
        $grid = $this->widget(RenderProductListWidget::CORE_TEMPLATE);
        $carousel = $this->widget(RenderProductListWidget::CORE_CAROUSEL_TEMPLATE);

        $this->plugin()->beforeToHtml($grid);
        $this->plugin()->beforeToHtml($carousel);

        $this->assertNotSame($grid->getTemplate(), $carousel->getTemplate());
    }

    public function testHandsTheCardOverToTheCarouselAsWell(): void
    {
        $widget = $this->widget(RenderProductListWidget::CORE_CAROUSEL_TEMPLATE);
        $card = $this->createMock(ProductCard::class);

        (new RenderProductListWidget($card))->beforeToHtml($widget);

        $this->assertSame($card, $widget->getData('card'));
    }

    private function plugin(): RenderProductListWidget
    {
        return new RenderProductListWidget($this->createMock(ProductCard::class));
    }

    /**
     * A real block without its constructor: the template and the data bag are
     * the block's own, so what is asserted is the behaviour rather than a mock's
     * bookkeeping.
     */
    private function widget(string $template): ProductsList
    {
        $widget = (new ReflectionClass(ProductsList::class))->newInstanceWithoutConstructor();
        $widget->setTemplate($template);

        return $widget;
    }
}
