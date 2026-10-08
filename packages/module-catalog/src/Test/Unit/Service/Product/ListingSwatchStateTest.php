<?php
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Catalog\Test\Unit\Service\Product;

use MageObsidian\Catalog\Service\Product\ConfigurableInitialState;
use MageObsidian\Catalog\Service\Product\ListingSwatchState;
use PHPUnit\Framework\TestCase;

class ListingSwatchStateTest extends TestCase
{
    private ListingSwatchState $state;

    protected function setUp(): void
    {
        $this->state = new ListingSwatchState(new ConfigurableInitialState());
    }

    private function config(): string
    {
        return (string)json_encode([
            'attributes' => [
                '93' => [
                    'id' => '93',
                    'code' => 'color',
                    'label' => 'Color',
                    'position' => '0',
                    'options' => [
                        ['id' => '50', 'label' => 'Blue'],
                        ['id' => '51', 'label' => 'Red'],
                        ['id' => '52', 'label' => 'Green'],
                    ],
                ],
                '141' => [
                    'id' => '141',
                    'code' => 'size',
                    'label' => 'Size',
                    'position' => '1',
                    'options' => [
                        ['id' => '28', 'label' => 'S'],
                        ['id' => '29', 'label' => 'M'],
                    ],
                ],
                '150' => [
                    'id' => '150',
                    'code' => 'fit',
                    'label' => 'Fit',
                    'position' => '2',
                    'options' => [
                        ['id' => '70', 'label' => 'Slim'],
                    ],
                ],
            ],
            'index' => [
                '101' => ['93' => '50', '141' => '28', '150' => '70'],
                '102' => ['93' => '50', '141' => '29', '150' => '70'],
                '103' => ['93' => '51', '141' => '28', '150' => '70'],
            ],
        ]);
    }

    private function swatches(): string
    {
        return (string)json_encode([
            '93' => [
                '50' => ['type' => '1', 'value' => '#1f4ed8', 'label' => 'Blue'],
                '51' => ['type' => '2', 'value' => '/media/swatch/red.png', 'label' => 'Red'],
                '52' => ['type' => '1', 'value' => '#00ff00', 'label' => 'Green'],
                'additional_data' => '{}',
            ],
            '141' => [
                '28' => ['type' => '0', 'value' => 'S', 'label' => 'S'],
                '29' => ['type' => '0', 'value' => 'M', 'label' => 'M'],
            ],
        ]);
    }

    private function noImages(): callable
    {
        return static fn(string $variantId): ?array => null;
    }

    public function testOnlyAttributesThatHaveSwatchesBecomeGroups(): void
    {
        $groups = $this->state->build($this->config(), $this->swatches(), $this->noImages(), 16);

        $this->assertSame(['color', 'size'], array_column($groups, 'code'));
    }

    public function testOptionsNoVariantCarriesAreLeftOut(): void
    {
        $groups = $this->state->build($this->config(), $this->swatches(), $this->noImages(), 16);

        $this->assertSame(['50', '51'], array_column($groups[0]['options'], 'id'));
    }

    public function testNoSwatchDataYieldsNoGroups(): void
    {
        $this->assertSame([], $this->state->build($this->config(), '{}', $this->noImages(), 16));
        $this->assertSame([], $this->state->build('not json', $this->swatches(), $this->noImages(), 16));
    }

    public function testOptionKindsFollowTheSwatchType(): void
    {
        $groups = $this->state->build($this->config(), $this->swatches(), $this->noImages(), 16);

        $this->assertSame(['color', 'image'], array_column($groups[0]['options'], 'kind'));
        $this->assertSame(['text', 'text'], array_column($groups[1]['options'], 'kind'));
    }

    public function testAnOptionTakesTheImageOfTheFirstVariantThatCarriesIt(): void
    {
        $asked = [];
        $images = static function (string $variantId) use (&$asked): ?array {
            $asked[] = $variantId;
            return ['src' => "/m/{$variantId}.jpg", 'srcset' => "/m/{$variantId}.jpg 240w"];
        };

        $groups = $this->state->build($this->config(), $this->swatches(), $images, 16);

        $this->assertSame(
            ['src' => '/m/101.jpg', 'srcset' => '/m/101.jpg 240w'],
            $groups[0]['options'][0]['image']
        );
        $this->assertSame(
            ['src' => '/m/103.jpg', 'srcset' => '/m/103.jpg 240w'],
            $groups[0]['options'][1]['image']
        );
        $this->assertSame(['101', '103'], $asked);
    }

    public function testAVariantWithoutAnImageFallsThroughToTheNextOne(): void
    {
        $images = static fn(string $variantId): ?array => $variantId === '102'
            ? ['src' => '/m/102.jpg', 'srcset' => '']
            : null;

        $groups = $this->state->build($this->config(), $this->swatches(), $images, 16);

        $this->assertSame('/m/102.jpg', $groups[0]['options'][0]['image']['src']);
        $this->assertNull($groups[0]['options'][1]['image']);
    }

    public function testOnlyTheFirstVisualGroupCarriesImages(): void
    {
        $images = static fn(string $variantId): ?array => ['src' => "/m/{$variantId}.jpg", 'srcset' => ''];

        $groups = $this->state->build($this->config(), $this->swatches(), $images, 16);

        $this->assertNotNull($groups[0]['options'][0]['image']);
        $this->assertNull($groups[1]['options'][0]['image']);
    }

    public function testATextGroupAheadOfTheVisualOneDoesNotTakeTheImages(): void
    {
        $config = json_decode($this->config(), true);
        $config['attributes']['93']['position'] = '1';
        $config['attributes']['141']['position'] = '0';
        $images = static fn(string $variantId): ?array => ['src' => "/m/{$variantId}.jpg", 'srcset' => ''];

        $groups = $this->state->build((string)json_encode($config), $this->swatches(), $images, 16);

        $this->assertSame(['size', 'color'], array_column($groups, 'code'));
        $this->assertNull($groups[0]['options'][0]['image']);
        $this->assertNotNull($groups[1]['options'][0]['image']);
    }

    public function testWithoutAVisualGroupTheFirstOneCarriesTheImages(): void
    {
        $swatches = json_decode($this->swatches(), true);
        unset($swatches['93']);
        $images = static fn(string $variantId): ?array => ['src' => "/m/{$variantId}.jpg", 'srcset' => ''];

        $groups = $this->state->build($this->config(), (string)json_encode($swatches), $images, 16);

        $this->assertSame(['size'], array_column($groups, 'code'));
        $this->assertNotNull($groups[0]['options'][0]['image']);
    }

    public function testTheLimitHidesTheOptionsBeyondItAndCountsThem(): void
    {
        $groups = $this->state->build($this->config(), $this->swatches(), $this->noImages(), 1);

        $this->assertSame(['50'], array_column($groups[0]['options'], 'id'));
        $this->assertSame(1, $groups[0]['hidden']);
        $this->assertSame(1, $groups[1]['hidden']);
    }

    public function testNothingIsHiddenUnderTheLimit(): void
    {
        $groups = $this->state->build($this->config(), $this->swatches(), $this->noImages(), 16);

        $this->assertSame([0, 0], array_column($groups, 'hidden'));
    }

    private function purchaseConfig(): string
    {
        return (string)json_encode([
            'attributes' => [
                '93' => ['id' => '93', 'code' => 'color', 'label' => 'Color', 'position' => '0', 'options' => [
                    ['id' => '50', 'label' => 'Blue'],
                    ['id' => '51', 'label' => 'Red'],
                ]],
                '141' => ['id' => '141', 'code' => 'size', 'label' => 'Size', 'position' => '1', 'options' => [
                    ['id' => '28', 'label' => 'S'],
                    ['id' => '29', 'label' => 'M'],
                ]],
            ],
            'index' => [
                '101' => ['93' => '50', '141' => '28'],
                '102' => ['93' => '50', '141' => '29'],
                '103' => ['93' => '51', '141' => '28'],
            ],
            'optionPrices' => [
                '101' => ['finalPrice' => ['amount' => 49]],
                '102' => ['finalPrice' => ['amount' => 59.5]],
                '103' => ['finalPrice' => ['amount' => 49]],
            ],
        ]);
    }

    private function purchaseSwatches(): string
    {
        return (string)json_encode([
            '93' => ['50' => ['value' => '#1f4ed8'], '51' => ['value' => '#ff0000']],
            '141' => ['28' => ['value' => 'S'], '29' => ['value' => 'M']],
        ]);
    }

    private function purchase(?string $config = null, ?string $swatches = null): array
    {
        $config ??= $this->purchaseConfig();
        $groups = $this->state->build($config, $swatches ?? $this->purchaseSwatches(), $this->noImages(), 16);

        return $this->state->purchase($config, $groups, static fn(float $amount): string => sprintf('$%.2f', $amount));
    }

    public function testEveryAvailableCombinationMapsToItsFormattedPriceAndChild(): void
    {
        $purchase = $this->purchase();

        $this->assertSame(['color', 'size'], $purchase['attributes']);
        $this->assertSame(
            [
                '50,28' => ['price' => '$49.00', 'id' => 101],
                '50,29' => ['price' => '$59.50', 'id' => 102],
                '51,28' => ['price' => '$49.00', 'id' => 103],
            ],
            $purchase['variants']
        );
    }

    public function testACombinationNoVariantCarriesIsAbsentFromTheMap(): void
    {
        $this->assertArrayNotHasKey('51,29', $this->purchase()['variants']);
    }

    public function testTheKeyFollowsTheOrderOfTheGroups(): void
    {
        $config = json_decode($this->purchaseConfig(), true);
        $config['attributes']['93']['position'] = '1';
        $config['attributes']['141']['position'] = '0';

        $purchase = $this->purchase((string)json_encode($config));

        $this->assertSame(['size', 'color'], $purchase['attributes']);
        $this->assertArrayHasKey('29,50', $purchase['variants']);
    }

    public function testNothingIsOfferedWhenAnAttributeHasNoSwatchGroup(): void
    {
        $swatches = json_decode($this->purchaseSwatches(), true);
        unset($swatches['141']);

        $this->assertSame([], $this->purchase(null, (string)json_encode($swatches)));
    }

    public function testAVariantWithoutAPriceIsLeftOut(): void
    {
        $config = json_decode($this->purchaseConfig(), true);
        unset($config['optionPrices']['102']);

        $this->assertArrayNotHasKey('50,29', $this->purchase((string)json_encode($config))['variants']);
    }

    public function testNoGroupsOrNoVariantsYieldNoPurchase(): void
    {
        $this->assertSame([], $this->state->purchase($this->purchaseConfig(), [], static fn(float $amount): string => ''));
        $this->assertSame([], $this->purchase('not json'));
    }
}
