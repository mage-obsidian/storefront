<?php
declare(strict_types=1);

namespace MageObsidian\Catalog\Test\Unit\ViewModel;

use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Framework\View\LayoutInterface;
use Magento\LayeredNavigation\Block\Navigation;
use Magento\LayeredNavigation\Block\Navigation\State;
use MageObsidian\Catalog\ViewModel\LayeredFilterState;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Filter summary the product-list toolbar reads to decide whether to render its
 * mobile trigger. Needs Magento LayeredNavigation types, so it runs in a Magento
 * root.
 */
class LayeredFilterStateTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Navigation::class)) {
            $this->markTestSkipped('Magento LayeredNavigation is not available in this runtime.');
        }
    }

    public function testHasNoFiltersWhenTheSidebarBlockIsAbsent(): void
    {
        $subject = new LayeredFilterState($this->layoutReturning(false));

        $this->assertFalse($subject->hasFilters());
        $this->assertSame(0, $subject->getActiveCount());
    }

    public function testHasNoFiltersWhenTheSidebarDeclinesToRender(): void
    {
        $navigation = $this->navigation(canShow: false);

        $this->assertFalse((new LayeredFilterState($this->layoutReturning($navigation)))->hasFilters());
    }

    public function testHasNoFiltersWhenEveryFilterIsEmpty(): void
    {
        $navigation = $this->navigation(filters: [$this->filter(0), $this->filter(0)]);

        $this->assertFalse((new LayeredFilterState($this->layoutReturning($navigation)))->hasFilters());
    }

    public function testHasFiltersWhenOneCarriesOptions(): void
    {
        $navigation = $this->navigation(filters: [$this->filter(0), $this->filter(3)]);

        $this->assertTrue((new LayeredFilterState($this->layoutReturning($navigation)))->hasFilters());
    }

    public function testCountsTheAppliedFilters(): void
    {
        $state = $this->createMock(State::class);
        $state->method('getActiveFilters')->willReturn(['color', 'size']);
        $navigation = $this->navigation(state: $state);

        $this->assertSame(2, (new LayeredFilterState($this->layoutReturning($navigation)))->getActiveCount());
    }

    public function testCountsZeroWithoutAStateChild(): void
    {
        $navigation = $this->navigation(state: null);

        $this->assertSame(0, (new LayeredFilterState($this->layoutReturning($navigation)))->getActiveCount());
    }

    /**
     * A sidebar that cannot resolve its layer must not take the listing down with
     * it — the trigger simply does not render.
     */
    public function testSwallowsAFailingSidebar(): void
    {
        $navigation = $this->createMock(Navigation::class);
        $navigation->method('canShowBlock')->willThrowException(new RuntimeException('no layer'));
        $subject = new LayeredFilterState($this->layoutReturning($navigation));

        $this->assertFalse($subject->hasFilters());
    }

    public function testSwallowsAFailingStateChild(): void
    {
        $navigation = $this->createMock(Navigation::class);
        $navigation->method('getChildBlock')->willThrowException(new RuntimeException('no state'));

        $this->assertSame(0, (new LayeredFilterState($this->layoutReturning($navigation)))->getActiveCount());
    }

    public function testReadsTheBlockNameItWasGiven(): void
    {
        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects($this->once())
            ->method('getBlock')
            ->with('catalogsearch.leftnav')
            ->willReturn(false);

        (new LayeredFilterState($layout, 'catalogsearch.leftnav'))->hasFilters();
    }

    public function testDefaultsToTheCategorySidebar(): void
    {
        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects($this->once())
            ->method('getBlock')
            ->with(LayeredFilterState::DEFAULT_BLOCK_NAME)
            ->willReturn(false);

        (new LayeredFilterState($layout))->hasFilters();
    }

    /**
     * @param mixed $block
     * @return LayoutInterface
     */
    private function layoutReturning(mixed $block): LayoutInterface
    {
        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('getBlock')->willReturn($block);

        return $layout;
    }

    /**
     * @param bool $canShow
     * @param array $filters
     * @param State|null $state
     * @return Navigation
     */
    private function navigation(bool $canShow = true, array $filters = [], ?State $state = null): Navigation
    {
        $navigation = $this->createMock(Navigation::class);
        $navigation->method('canShowBlock')->willReturn($canShow);
        $navigation->method('getFilters')->willReturn($filters);
        $navigation->method('getChildBlock')->willReturn($state ?? false);

        return $navigation;
    }

    /**
     * @param int $itemsCount
     * @return AbstractFilter
     */
    private function filter(int $itemsCount): AbstractFilter
    {
        $filter = $this->createMock(AbstractFilter::class);
        $filter->method('getItemsCount')->willReturn($itemsCount);

        return $filter;
    }
}
