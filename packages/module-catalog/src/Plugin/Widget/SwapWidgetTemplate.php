<?php
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Catalog\Plugin\Widget;

use Magento\Framework\View\Element\Template;
use MageObsidian\Catalog\ViewModel\ProductCard;
use MageObsidian\Catalog\ViewModel\RecentlyProducts;
use MageObsidian\ModernFrontend\Service\Theme\ObsidianThemeDetector;

class SwapWidgetTemplate
{
    public const string NEW_GRID = 'Magento_Catalog::product/widget/new/content/new_grid.twig';

    public const string NEW_COLUMN = 'Magento_Catalog::product/widget/new/column/new_column.twig';

    public const string RECENT = 'Magento_Catalog::product/widget/recent.twig';

    public const array TEMPLATES = [
        'product/widget/new/content/new_grid.phtml' => [self::NEW_GRID, []],
        'product/widget/new/content/new_list.phtml' => [self::NEW_GRID, []],
        'product/widget/new/column/new_default_list.phtml' => [self::NEW_COLUMN, ['column_variant' => 'default']],
        'product/widget/new/column/new_names_list.phtml' => [self::NEW_COLUMN, ['column_variant' => 'names']],
        'product/widget/new/column/new_images_list.phtml' => [self::NEW_COLUMN, ['column_variant' => 'images']],
        'product/widget/viewed/grid.phtml' => [self::RECENT, ['recent_kind' => 'viewed']],
        'product/widget/viewed/list.phtml' => [self::RECENT, ['recent_kind' => 'viewed']],
        'product/widget/viewed/sidebar.phtml' => [self::RECENT, ['recent_kind' => 'viewed']],
        'product/widget/compared/grid.phtml' => [self::RECENT, ['recent_kind' => 'compared']],
        'product/widget/compared/list.phtml' => [self::RECENT, ['recent_kind' => 'compared']],
        'product/widget/compared/sidebar.phtml' => [self::RECENT, ['recent_kind' => 'compared']],
    ];

    public function __construct(
        private readonly ProductCard $card,
        private readonly RecentlyProducts $recently,
        private readonly ObsidianThemeDetector $themeDetector
    ) {
    }

    public function beforeToHtml(Template $subject): void
    {
        if (!$this->themeDetector->isActive()) {
            return;
        }

        $key = $this->keyOf((string)$subject->getTemplate());
        if (!isset(self::TEMPLATES[$key])) {
            return;
        }

        [$template, $data] = self::TEMPLATES[$key];
        $subject->setTemplate($template);
        $subject->addData($data);
        if ($subject->getData('card') === null) {
            $subject->setData('card', $this->card);
        }
        if ($subject->getData('recently') === null) {
            $subject->setData('recently', $this->recently);
        }
    }

    private function keyOf(string $template): string
    {
        $position = strpos($template, '::');

        return $position === false ? $template : substr($template, $position + 2);
    }
}
