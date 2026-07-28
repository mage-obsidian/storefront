<?php
declare(strict_types=1);

namespace MageObsidian\Catalog\Test\Unit\Service\Product;

use MageObsidian\Catalog\Service\Product\ConfigurableInitialState;
use PHPUnit\Framework\TestCase;

class ConfigurableInitialStateTest extends TestCase
{
    private ConfigurableInitialState $state;

    protected function setUp(): void
    {
        $this->state = new ConfigurableInitialState();
    }

    private function config(): string
    {
        return (string)json_encode([
            'attributes' => [
                '93' => [
                    'id' => '93',
                    'label' => 'Color',
                    'position' => '1',
                    'options' => [
                        ['id' => '50', 'label' => 'Blue'],
                        ['id' => '51', 'label' => 'Red'],
                    ],
                ],
                '141' => [
                    'id' => '141',
                    'label' => 'Size',
                    'position' => '0',
                    'options' => [
                        ['id' => '28', 'label' => '28'],
                        ['id' => '29', 'label' => '29'],
                    ],
                ],
            ],
            'index' => [
                '101' => ['141' => '28', '93' => '50'],
                '102' => ['141' => '29', '93' => '50'],
            ],
        ]);
    }

    public function testGroupsFollowTheAttributePositionNotTheJsonKeyOrder(): void
    {
        $groups = $this->state->build($this->config());

        $this->assertSame(['Size', 'Color'], array_column($groups, 'label'));
    }

    public function testOptionsCarryTheirIdAndLabel(): void
    {
        $groups = $this->state->build($this->config());

        $this->assertSame(['28', '29'], array_column($groups[0]['options'], 'id'));
        $this->assertSame(['Blue', 'Red'], array_column($groups[1]['options'], 'label'));
    }

    public function testAnOptionNoVariantCarriesIsUnavailable(): void
    {
        // Red (51) appears in no row of the index, so no variant can have it.
        $groups = $this->state->build($this->config());

        $this->assertSame([true, false], array_column($groups[1]['options'], 'available'));
    }

    public function testAHexValueIsAColourSwatch(): void
    {
        $swatches = (string)json_encode(['93' => ['50' => ['value' => '#1f4ed8']]]);

        $groups = $this->state->build($this->config(), $swatches);

        $this->assertSame('color', $groups[1]['options'][0]['kind']);
        $this->assertSame('#1f4ed8', $groups[1]['options'][0]['value']);
    }

    public function testAPathValueIsAnImageSwatch(): void
    {
        $swatches = (string)json_encode(['93' => ['50' => ['value' => '/media/swatch/b.png']]]);

        $groups = $this->state->build($this->config(), $swatches);

        $this->assertSame('image', $groups[1]['options'][0]['kind']);
        $this->assertSame('/media/swatch/b.png', $groups[1]['options'][0]['value']);
    }

    public function testAnythingElseFallsBackToATextSwatch(): void
    {
        $groups = $this->state->build($this->config(), (string)json_encode([]));

        $this->assertSame('text', $groups[0]['options'][0]['kind']);
        $this->assertSame('', $groups[0]['options'][0]['value']);
    }

    public function testMalformedJsonYieldsNoGroupsInsteadOfFailing(): void
    {
        // A broken config must leave the island to mount client-side, not break
        // the whole product page.
        $this->assertSame([], $this->state->build('not json'));
        $this->assertSame([], $this->state->build(''));
    }
}
