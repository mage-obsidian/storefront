<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Showcase\Test\Unit\Model;

use Magento\Framework\Module\ModuleListInterface;
use MageObsidian\Showcase\Model\FeaturePool;
use MageObsidian\Showcase\Model\FeatureType;
use PHPUnit\Framework\TestCase;

class FeaturePoolTest extends TestCase
{
    private function pool(array $features, array $installed = []): FeaturePool
    {
        $moduleList = $this->createMock(ModuleListInterface::class);
        $moduleList->method('getOne')->willReturnCallback(
            static fn (string $name): ?array => in_array($name, $installed, true) ? ['name' => $name] : null
        );

        return new FeaturePool($moduleList, $features);
    }

    public function testBuildsADeclaredFeature(): void
    {
        $pool = $this->pool([
            'listing_fragments' => [
                'path' => 'mage_obsidian/listing/fragments_enabled',
                'module' => 'MageObsidian_Search',
                'label' => 'Fragments',
                'description' => 'Only the regions that change.',
            ],
        ], ['MageObsidian_Search']);

        $feature = $pool->get('listing_fragments');

        $this->assertNotNull($feature);
        $this->assertSame('mage_obsidian/listing/fragments_enabled', $feature->path);
        $this->assertSame(FeatureType::Flag, $feature->type);
        $this->assertSame('Only the regions that change.', $feature->description);
    }

    // A demo carries switches for modules it has not installed yet.
    public function testSkipsAFeatureWhoseModuleIsAbsent(): void
    {
        $pool = $this->pool([
            'stock_visualizer' => [
                'path' => 'cataloginventory/stock_visualizer/enabled',
                'module' => 'MageObsidian_InventoryStockVisualizer',
            ],
        ]);

        $this->assertSame([], $pool->available());
    }

    public function testSkipsAnEntryWithNoPath(): void
    {
        $pool = $this->pool(['broken' => ['label' => 'Nothing to switch']]);

        $this->assertSame([], $pool->available());
    }

    public function testAFeatureWithNoModuleIsAlwaysAvailable(): void
    {
        $pool = $this->pool(['free' => ['path' => 'some/config/path']]);

        $this->assertArrayHasKey('free', $pool->available());
    }

    public function testReadsAChoiceWithItsOptions(): void
    {
        $pool = $this->pool([
            'checkout_layout' => [
                'path' => 'mage_obsidian/checkout/layout_mode',
                'type' => 'choice',
                'options' => ['stepped' => 'Stepped', 'onepage' => 'One page'],
            ],
        ]);

        $feature = $pool->get('checkout_layout');

        $this->assertSame(FeatureType::Choice, $feature->type);
        $this->assertTrue($feature->accepts('onepage'));
        $this->assertFalse($feature->accepts('1'));
    }

    public function testAFlagOnlyAcceptsOneOrZero(): void
    {
        $feature = $this->pool(['f' => ['path' => 'a/b/c']])->get('f');

        $this->assertTrue($feature->accepts('1'));
        $this->assertTrue($feature->accepts('0'));
        $this->assertFalse($feature->accepts('yes'));
    }

    public function testUnknownKeyIsNull(): void
    {
        $this->assertNull($this->pool([])->get('nope'));
    }
}
