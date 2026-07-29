<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

class NavOverflow implements ArgumentInterface
{
    public const string OPTION_CONTAINER = 'container';
    public const string OPTION_TRACKING = 'tracking';
    public const string OPTION_CHEVRON = 'chevron';
    public const string OPTION_MORE_LABEL = 'moreLabel';
    public const string OPTION_MORE_EXTRA = 'moreExtra';
    public const string OPTION_GAPS = 'gaps';
    public const string OPTION_UPPERCASE = 'uppercase';

    public const string GAP_FROM = 'from';
    public const string GAP_SIZE = 'gap';

    public const string ATTR_INDEX = 'data-nav-index';
    public const string ATTR_OVERFLOW_INDEX = 'data-nav-overflow-index';
    public const string ATTR_MORE = 'data-nav-more';

    private const string DEFAULT_CONTAINER = 'obsidian-nav';
    private const float DEFAULT_TRACKING = 0.18;
    private const float DEFAULT_CHEVRON = 16.0;
    private const string DEFAULT_MORE_LABEL = 'More';
    private const float DEFAULT_MORE_EXTRA = 18.0;
    private const float DEFAULT_GAP = 24.0;

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $options
     */
    public function getStyle(array $items, array $options = []): string
    {
        $uppercase = (bool)($options[self::OPTION_UPPERCASE] ?? false);
        $widths = [];
        foreach ($items as $item) {
            $widths[] = [
                $this->cells((string)($item['label'] ?? ''), $uppercase),
                empty($item['children']) ? 0.0 : (float)($options[self::OPTION_CHEVRON] ?? self::DEFAULT_CHEVRON),
            ];
        }
        if ($widths === []) {
            return '';
        }

        $container = (string)($options[self::OPTION_CONTAINER] ?? self::DEFAULT_CONTAINER);
        $unit = $this->unit((float)($options[self::OPTION_TRACKING] ?? self::DEFAULT_TRACKING));
        $moreCells = $this->cells(
            (string)($options[self::OPTION_MORE_LABEL] ?? self::DEFAULT_MORE_LABEL),
            $uppercase
        );
        $moreExtra = (float)($options[self::OPTION_MORE_EXTRA] ?? self::DEFAULT_MORE_EXTRA);

        $css = '';
        foreach ($this->bands($options) as $band) {
            $rules = $this->rules($widths, $container, $unit, $moreCells, $moreExtra, $band[self::GAP_SIZE]);
            $css .= $band['query'] === '' ? $rules : sprintf('%s{%s}', $band['query'], $rules);
        }

        return $css;
    }

    /**
     * @param array<int, array{0: int, 1: float}> $widths
     */
    private function rules(
        array $widths,
        string $container,
        string $unit,
        int $moreCells,
        float $moreExtra,
        float $gap
    ): string {
        $allCells = array_sum(array_column($widths, 0));
        $allPixels = array_sum(array_column($widths, 1)) + (count($widths) - 1) * $gap;
        $whole = $this->length($allCells, $allPixels, $unit);

        $css = '';
        $cells = 0;
        $pixels = 0.0;
        foreach ($widths as $index => [$itemCells, $chevron]) {
            $cells += $itemCells;
            $pixels += $chevron;
            $fits = $this->length($cells + $moreCells, $pixels + ($index + 1) * $gap + $moreExtra, $unit);
            $css .= sprintf(
                '@container %1$s ((width < %2$s) and (width < %3$s)){[%4$s="%5$d"]{display:none}}'
                . '@container %1$s ((width >= %2$s) or (width >= %3$s)){[%6$s="%5$d"]{display:none}}',
                $container,
                $fits,
                $whole,
                self::ATTR_INDEX,
                $index,
                self::ATTR_OVERFLOW_INDEX
            );
        }

        return $css . sprintf(
            '@container %s (width >= %s){[%s]{display:none}}',
            $container,
            $whole,
            self::ATTR_MORE
        );
    }

    /**
     * @param array<string, mixed> $options
     * @return array<int, array{gap: float, query: string}>
     */
    private function bands(array $options): array
    {
        /** @var array<int, array{from?: float|int, gap: float|int}> $configured */
        $configured = $options[self::OPTION_GAPS] ?? [];
        if ($configured === []) {
            return [[self::GAP_SIZE => (float)(self::DEFAULT_GAP), 'query' => '']];
        }

        usort(
            $configured,
            static fn(array $a, array $b): int => ($a[self::GAP_FROM] ?? 0) <=> ($b[self::GAP_FROM] ?? 0)
        );
        if (count($configured) === 1) {
            return [[self::GAP_SIZE => (float)$configured[0][self::GAP_SIZE], 'query' => '']];
        }

        $bands = [];
        foreach ($configured as $position => $band) {
            $from = (float)($band[self::GAP_FROM] ?? 0);
            $next = $configured[$position + 1][self::GAP_FROM] ?? null;
            $conditions = [];
            if ($from > 0) {
                $conditions[] = sprintf('(width >= %spx)', $this->number($from));
            }
            if ($next !== null) {
                $conditions[] = sprintf('(width < %spx)', $this->number((float)$next));
            }
            $bands[] = [
                self::GAP_SIZE => (float)$band[self::GAP_SIZE],
                'query' => '@media ' . implode(' and ', $conditions),
            ];
        }

        return $bands;
    }

    private function unit(float $tracking): string
    {
        return $tracking > 0.0 ? sprintf('(1ch + %sem)', $this->number($tracking)) : '1ch';
    }

    private function length(int $cells, float $pixels, string $unit): string
    {
        $terms = sprintf('%d*%s', $cells, $unit);
        if (abs($pixels) > 0.0001) {
            $terms .= sprintf(' + %spx', $this->number($pixels));
        }

        return sprintf('calc(%s)', $terms);
    }

    private function cells(string $label, bool $uppercase): int
    {
        $label = trim($label);

        return mb_strwidth($uppercase ? mb_strtoupper($label) : $label);
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
}
