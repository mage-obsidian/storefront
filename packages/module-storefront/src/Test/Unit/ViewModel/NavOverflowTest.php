<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use MageObsidian\Storefront\ViewModel\NavOverflow;
use PHPUnit\Framework\TestCase;

class NavOverflowTest extends TestCase
{
    private const float CH = 6.912;
    private const float EM = 11.52;

    /** @var array<int, array{label: string, children?: array<int, array<string, string>>}> */
    private const LIVE_ITEMS = [
        ['label' => 'Category 9', 'children' => [['label' => 'x']]],
        ['label' => "What's New"],
        ['label' => 'Category 10', 'children' => [['label' => 'x']]],
        ['label' => 'Category 11', 'children' => [['label' => 'x']]],
        ['label' => 'Women', 'children' => [['label' => 'x']]],
        ['label' => 'Men', 'children' => [['label' => 'x']]],
        ['label' => 'Category 12', 'children' => [['label' => 'x']]],
        ['label' => 'Category 13', 'children' => [['label' => 'x']]],
    ];

    private function viewModel(): NavOverflow
    {
        return new NavOverflow();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function style(array $items, array $options = []): string
    {
        return $this->viewModel()->getStyle($items, $options);
    }

    private function itemThreshold(string $css, int $index): float
    {
        return $this->condition($css, NavOverflow::ATTR_INDEX, $index, 'width < ');
    }

    private function panelThreshold(string $css, int $index): float
    {
        return $this->condition($css, NavOverflow::ATTR_OVERFLOW_INDEX, $index, 'width >= ');
    }

    private function condition(string $css, string $attribute, int $index, string $comparison): float
    {
        $pattern = sprintf(
            '/@container [\w-]+ \(([^{]*)\)\{\[%s="%d"\]\{display:none\}\}/',
            preg_quote($attribute, '/'),
            $index
        );
        self::assertSame(1, preg_match($pattern, $css, $matches), "No rule for item {$index}");
        self::assertSame(
            2,
            preg_match_all('/' . preg_quote($comparison, '/') . '/', $matches[1]),
            "Expected both bounds in: {$matches[1]}"
        );

        preg_match_all('/calc\([^)]*\)[^)]*\)/', $matches[1], $lengths);

        return min(array_map(fn(string $length): float => $this->evaluate($length), $lengths[0]));
    }

    private function evaluate(string $length): float
    {
        $matched = preg_match('/^calc\((\d+)\*\(1ch \+ ([\d.]+)em\)(?: \+ ([\d.]+)px)?\)$/', $length, $parts);
        self::assertSame(1, $matched, "Unparsable length: {$length}");

        return (int)$parts[1] * (self::CH + (float)$parts[2] * self::EM) + (float)($parts[3] ?? 0);
    }

    public function testNoItemsProduceNoStyleAtAll(): void
    {
        self::assertSame('', $this->style([]));
    }

    public function testThresholdsReproduceTheCutMeasuredInTheBrowser(): void
    {
        $narrow = $this->style(self::LIVE_ITEMS, [NavOverflow::OPTION_GAPS => [[NavOverflow::GAP_SIZE => 24]]]);
        $wide = $this->style(self::LIVE_ITEMS, [NavOverflow::OPTION_GAPS => [[NavOverflow::GAP_SIZE => 32]]]);

        self::assertEqualsWithDelta(436.50, $this->itemThreshold($narrow, 2), 0.1);
        self::assertEqualsWithDelta(575.28, $this->itemThreshold($narrow, 3), 0.1);
        self::assertEqualsWithDelta(700.20, $this->itemThreshold($wide, 4), 0.1);
        self::assertEqualsWithDelta(775.22, $this->itemThreshold($wide, 5), 0.1);

        foreach ([[505.72, 3, $narrow], [633.72, 4, $narrow], [761.72, 5, $wide], [816.72, 6, $wide]] as $case) {
            [$available, $expected, $css] = $case;
            $visible = 0;
            foreach (array_keys(self::LIVE_ITEMS) as $index) {
                $visible += $this->itemThreshold($css, $index) <= $available ? 1 : 0;
            }
            self::assertSame($expected, $visible, "Wrong cut at {$available}px");
        }
    }

    public function testTheBarAndThePanelSplitOnTheSameThreshold(): void
    {
        $css = $this->style(self::LIVE_ITEMS);

        foreach (array_keys(self::LIVE_ITEMS) as $index) {
            self::assertEqualsWithDelta(
                $this->itemThreshold($css, $index),
                $this->panelThreshold($css, $index),
                0.0001,
                "Item {$index} would be in both places, or in neither"
            );
        }
    }

    public function testMoreTriggerDisappearsOnceTheWholeRowFitsWithoutIt(): void
    {
        $css = $this->style([['label' => 'Sale'], ['label' => 'New in']], [
            NavOverflow::OPTION_GAPS => [[NavOverflow::GAP_SIZE => 24]],
        ]);

        $pattern = sprintf(
            '/\(width >= (calc\([^)]*\)[^)]*\))\)\{\[%s\]\{display:none\}\}/',
            preg_quote(NavOverflow::ATTR_MORE, '/')
        );
        self::assertSame(1, preg_match($pattern, $css, $matches));

        self::assertEqualsWithDelta(10 * (self::CH + 0.18 * self::EM) + 24, $this->evaluate($matches[1]), 0.0001);
    }

    public function testItemsSurviveTheWidthBandWhereTheTriggerIsAlreadyGone(): void
    {
        $items = [['label' => 'Sale'], ['label' => 'New in']];
        $css = $this->style($items, [NavOverflow::OPTION_GAPS => [[NavOverflow::GAP_SIZE => 24]]]);

        $wholeRow = 10 * (self::CH + 0.18 * self::EM) + 24;
        foreach ([$wholeRow, $wholeRow + 40, $wholeRow + 80] as $available) {
            foreach (array_keys($items) as $index) {
                self::assertLessThanOrEqual(
                    $available,
                    $this->itemThreshold($css, $index),
                    sprintf('Item %d vanished at %.2fpx with no trigger to fall into', $index, $available)
                );
            }
        }
    }

    public function testGapBandsAreEmittedAsMutuallyExclusiveMediaBlocks(): void
    {
        $css = $this->style([['label' => 'Sale']], [
            NavOverflow::OPTION_GAPS => [
                [NavOverflow::GAP_FROM => 1280, NavOverflow::GAP_SIZE => 32],
                [NavOverflow::GAP_SIZE => 24],
            ],
        ]);

        self::assertStringContainsString('@media (width < 1280px){', $css);
        self::assertStringContainsString('@media (width >= 1280px){', $css);
        self::assertSame(2, substr_count($css, '@media '));
    }

    public function testASingleGapNeedsNoMediaQuery(): void
    {
        $css = $this->style([['label' => 'Sale']], [NavOverflow::OPTION_GAPS => [[NavOverflow::GAP_SIZE => 32]]]);

        self::assertStringNotContainsString('@media', $css);
    }

    public function testAnItemWithChildrenReservesTheChevronBox(): void
    {
        $plain = $this->style([['label' => 'Sale']]);
        $parent = $this->style([['label' => 'Sale', 'children' => [['label' => 'x']]]]);

        self::assertEqualsWithDelta(
            16.0,
            $this->itemThreshold($parent, 0) - $this->itemThreshold($plain, 0),
            0.0001
        );
    }

    public function testUppercasingCountsTheWidenedLabel(): void
    {
        $asIs = $this->style([['label' => 'straße']]);
        $upper = $this->style([['label' => 'straße']], [NavOverflow::OPTION_UPPERCASE => true]);

        self::assertEqualsWithDelta(
            self::CH + 0.18 * self::EM,
            $this->itemThreshold($upper, 0) - $this->itemThreshold($asIs, 0),
            0.0001
        );
    }

    public function testFullWidthGlyphsTakeTwoCells(): void
    {
        $latin = $this->style([['label' => 'ab']]);
        $wide = $this->style([['label' => '新品']]);

        self::assertEqualsWithDelta(
            2 * (self::CH + 0.18 * self::EM),
            $this->itemThreshold($wide, 0) - $this->itemThreshold($latin, 0),
            0.0001
        );
    }

    public function testTrackingIsOmittedWhenTheThemeHasNone(): void
    {
        $css = $this->style([['label' => 'Sale']], [NavOverflow::OPTION_TRACKING => 0.0]);

        self::assertStringContainsString('calc(8*1ch', $css);
        self::assertStringNotContainsString('em)', $css);
    }
}
