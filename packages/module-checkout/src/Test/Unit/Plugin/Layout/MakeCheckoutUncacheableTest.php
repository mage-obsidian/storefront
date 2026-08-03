<?php
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\Plugin\Layout;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\View\Layout;
use MageObsidian\Checkout\Model\Shell\Gate;
use MageObsidian\Checkout\Plugin\Layout\MakeCheckoutUncacheable;
use PHPUnit\Framework\TestCase;

/**
 * Turning the page cache off for the uncached checkout paths.
 *
 * A layout handle cannot carry this decision: measured live, `isCacheable()` runs
 * against more than one layout during a single checkout request, and only some of
 * them have the handle — the one that does not sets the public headers, and the
 * ?obsidian_shell=0 page (which inlines the quote and the masked cart id) ended up
 * in the page cache and was served to the next visitor. The request is the only
 * thing there is exactly one of, so the decision reads from it.
 */
class MakeCheckoutUncacheableTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Layout::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testTheCheckoutIsUncacheableWhenTheShellIsOff(): void
    {
        $plugin = $this->plugin(action: 'checkout_index_index', shellCacheable: false);

        $this->assertFalse($plugin->afterIsCacheable($this->layout(), true));
    }

    public function testTheCheckoutStaysCacheableWhenTheShellIsOn(): void
    {
        $plugin = $this->plugin(action: 'checkout_index_index', shellCacheable: true);

        $this->assertTrue($plugin->afterIsCacheable($this->layout(), true));
    }

    /**
     * The flag is off by default, so an unscoped plugin would make the entire
     * storefront uncacheable. Every other page must be untouched either way.
     */
    public function testEveryOtherPageIsLeftAloneEvenWithTheShellOff(): void
    {
        foreach (['catalog_category_view', 'cms_index_index', 'checkout_cart_index'] as $action) {
            $plugin = $this->plugin(action: $action, shellCacheable: false);

            $this->assertTrue(
                $plugin->afterIsCacheable($this->layout(), true),
                sprintf('made "%s" uncacheable', $action)
            );
        }
    }

    public function testItNeverTurnsCachingBackOn(): void
    {
        $plugin = $this->plugin(action: 'checkout_index_index', shellCacheable: true);

        $this->assertFalse($plugin->afterIsCacheable($this->layout(), false));
    }

    private function plugin(string $action, bool $shellCacheable): MakeCheckoutUncacheable
    {
        $gate = $this->createMock(Gate::class);
        $gate->method('isCacheable')->willReturn($shellCacheable);

        $request = $this->createMock(HttpRequest::class);
        $request->method('getFullActionName')->willReturn($action);

        return new MakeCheckoutUncacheable($gate, $request);
    }

    private function layout(): Layout
    {
        return $this->createMock(Layout::class);
    }
}
