<?php
declare(strict_types=1);

namespace MageObsidian\Catalog\Test\Unit\Plugin\ConfigurableProduct;

use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use MageObsidian\Catalog\Plugin\ConfigurableProduct\CacheParentIdsByChild;
use PHPUnit\Framework\TestCase;

/**
 * The lookup is asked once per product per price render, so the same child is
 * resolved twice on a listing. Needs Magento ConfigurableProduct types, so it
 * runs in a Magento root (see phpunit.ci.xml).
 */
class CacheParentIdsByChildTest extends TestCase
{
    private Configurable $subject;

    private CacheParentIdsByChild $plugin;

    protected function setUp(): void
    {
        if (!class_exists(Configurable::class)) {
            $this->markTestSkipped('Magento ConfigurableProduct is not available in this runtime.');
        }
        $this->subject = $this->createMock(Configurable::class);
        $this->plugin = new CacheParentIdsByChild();
    }

    public function testItAsksTheResourceOncePerChild(): void
    {
        $calls = [];
        $proceed = static function ($childId) use (&$calls): array {
            $calls[] = $childId;
            return ['7'];
        };

        $this->assertSame(['7'], $this->plugin->aroundGetParentIdsByChild($this->subject, $proceed, 3));
        $this->assertSame(['7'], $this->plugin->aroundGetParentIdsByChild($this->subject, $proceed, 3));
        $this->assertSame(['7'], $this->plugin->aroundGetParentIdsByChild($this->subject, $proceed, '3'));

        $this->assertSame([3], $calls);
    }

    public function testItKeepsAnEmptyAnswerInsteadOfAskingAgain(): void
    {
        $calls = 0;
        $proceed = static function () use (&$calls): array {
            $calls++;
            return [];
        };

        $this->assertSame([], $this->plugin->aroundGetParentIdsByChild($this->subject, $proceed, 9));
        $this->assertSame([], $this->plugin->aroundGetParentIdsByChild($this->subject, $proceed, 9));

        $this->assertSame(1, $calls);
    }

    public function testItKeepsChildrenApart(): void
    {
        $proceed = static fn ($childId): array => [(string)((int)$childId * 10)];

        $this->assertSame(['10'], $this->plugin->aroundGetParentIdsByChild($this->subject, $proceed, 1));
        $this->assertSame(['20'], $this->plugin->aroundGetParentIdsByChild($this->subject, $proceed, 2));
        $this->assertSame(['10'], $this->plugin->aroundGetParentIdsByChild($this->subject, $proceed, 1));
    }

    public function testABatchCallGoesStraightThroughBecauseItsAnswerCannotBeAttributed(): void
    {
        $calls = 0;
        $proceed = static function () use (&$calls): array {
            $calls++;
            return ['7', '8'];
        };

        $this->assertSame(['7', '8'], $this->plugin->aroundGetParentIdsByChild($this->subject, $proceed, [1, 2]));
        $this->assertSame(['7', '8'], $this->plugin->aroundGetParentIdsByChild($this->subject, $proceed, [1, 2]));

        $this->assertSame(2, $calls);
    }
}
