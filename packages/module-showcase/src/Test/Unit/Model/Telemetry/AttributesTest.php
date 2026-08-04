<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Showcase\Test\Unit\Model\Telemetry;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Showcase\Model\Config;
use MageObsidian\Showcase\Model\FeaturePool;
use MageObsidian\Showcase\Model\Profile;
use MageObsidian\Showcase\Model\Telemetry\Attributes;
use MageObsidian\Showcase\Model\Telemetry\AutoloadTimer;
use PHPUnit\Framework\TestCase;

class AttributesTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_GET[Attributes::FRAGMENT_PARAMETER]);
    }

    public function testReportsTheEffectiveValueOfEveryAvailableFeature(): void
    {
        $attributes = $this->attributes(
            features: ['listing_fragments' => 'mage_obsidian/listing/fragments_enabled'],
            effective: ['mage_obsidian/listing/fragments_enabled' => '1']
        );

        $this->assertSame('1', $attributes->collect()['obsidian.listing_fragments']);
    }

    // A choice feature carries its value, not a flag, and that is what makes the
    // two checkout layouts comparable against each other.
    public function testReportsTheValueOfAChoiceFeature(): void
    {
        $attributes = $this->attributes(
            features: ['checkout_layout' => 'mage_obsidian/checkout/layout_mode'],
            effective: ['mage_obsidian/checkout/layout_mode' => 'onepage']
        );

        $this->assertSame('onepage', $attributes->collect()['obsidian.checkout_layout']);
    }

    // The pool already leaves out features whose module is absent; nothing that
    // is not switchable on this install should reach the agent.
    public function testReportsNothingForAFeatureThatIsNotAvailable(): void
    {
        $attributes = $this->attributes(features: [], effective: []);

        $this->assertArrayNotHasKey('obsidian.listing_fragments', $attributes->collect());
    }

    // Every request pays for this, so a store that never turned the showcase on
    // must not pay for reading the config of features it does not switch.
    public function testCollectsNothingWhenTheShowcaseIsDisabled(): void
    {
        $attributes = $this->attributes(
            features: ['listing_fragments' => 'mage_obsidian/listing/fragments_enabled'],
            effective: ['mage_obsidian/listing/fragments_enabled' => '1'],
            enabled: false
        );

        $this->assertSame([], $attributes->collect());
    }

    public function testReportsTheProfileOfAVisitorWhoOverrodeSomething(): void
    {
        $attributes = $this->attributes(
            features: [],
            effective: [],
            signature: 'listing_fragments=0'
        );

        $this->assertSame('listing_fragments=0', $attributes->collect()['obsidian.profile']);
    }

    // An empty signature is what most requests carry, and an empty string is
    // useless to facet on; these are the ones the store view alone explains.
    public function testReportsAVisitorWhoOverrodeNothingAsTheStoreItself(): void
    {
        $attributes = $this->attributes(features: [], effective: [], signature: '');

        $this->assertSame('store', $attributes->collect()['obsidian.profile']);
    }

    public function testReportsTheStoreView(): void
    {
        $attributes = $this->attributes(features: [], effective: [], storeCode: 'classic');

        $this->assertSame('classic', $attributes->collect()['obsidian.store']);
    }

    /**
     * Page and fragment run the same controller, so they land in one transaction
     * and average together unless something tells them apart.
     */
    public function testReportsARequestThatAskedForAFragment(): void
    {
        $_GET[Attributes::FRAGMENT_PARAMETER] = '1';

        $attributes = $this->attributes(features: [], effective: []);

        $this->assertSame('1', $attributes->collect()['obsidian.fragment']);
    }

    public function testReportsAWholePageRequest(): void
    {
        $attributes = $this->attributes(features: [], effective: []);

        $this->assertSame('0', $attributes->collect()['obsidian.fragment']);
    }

    /**
     * The figure only means anything as a number: New Relic types an attribute
     * from what it is handed, and `average()` on a string one returns null.
     */
    public function testReportsTheAutoloadCostAsNumbers(): void
    {
        $attributes = $this->attributes(
            features: [],
            effective: [],
            autoloadMs: 12.5,
            autoloadClasses: 1840
        );

        $collected = $attributes->collect();

        $this->assertSame(12.5, $collected['obsidian.autoload_ms']);
        $this->assertSame(1840, $collected['obsidian.autoload_classes']);
    }

    // Without the prepend there is no measurement, and reporting a zero would
    // drag down every average that includes these requests.
    public function testOmitsTheAutoloadCostWhenItWasNotMeasured(): void
    {
        $collected = $this->attributes(features: [], effective: [])->collect();

        $this->assertArrayNotHasKey('obsidian.autoload_ms', $collected);
        $this->assertArrayNotHasKey('obsidian.autoload_classes', $collected);
    }

    /**
     * @param array<string, string> $features key => config path
     * @param array<string, string> $effective config path => value in force
     */
    private function attributes(
        array $features,
        array $effective,
        bool $enabled = true,
        string $signature = '',
        string $storeCode = 'default',
        ?float $autoloadMs = null,
        ?int $autoloadClasses = null
    ): Attributes {
        $config = $this->createMock(Config::class);
        $config->method('isEnabled')->willReturn($enabled);

        $moduleList = $this->createMock(ModuleListInterface::class);
        $moduleList->method('getOne')->willReturn(['name' => 'Any_Module']);
        $definitions = [];
        foreach ($features as $key => $path) {
            $definitions[$key] = ['path' => $path];
        }

        $profile = $this->createMock(Profile::class);
        $profile->method('signature')->willReturn($signature);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): ?string => $effective[$path] ?? null
        );

        $store = $this->createMock(StoreInterface::class);
        $store->method('getCode')->willReturn($storeCode);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $autoloadTimer = $this->createMock(AutoloadTimer::class);
        $autoloadTimer->method('milliseconds')->willReturn($autoloadMs);
        $autoloadTimer->method('classes')->willReturn($autoloadClasses);

        return new Attributes(
            $config,
            new FeaturePool($moduleList, $definitions),
            $profile,
            $scopeConfig,
            $storeManager,
            $autoloadTimer
        );
    }
}
