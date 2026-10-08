<?php
/**
 * This file is part of the MageObsidian - Sales project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Sales\Plugin\Widget;

use Magento\Framework\View\Element\Template;
use MageObsidian\ModernFrontend\Block\Template as IslandTemplate;
use MageObsidian\Sales\ViewModel\OrdersReturnsForm;

class RenderGuestFormWidget
{
    public const string CORE_TEMPLATE = 'widget/guest/form.phtml';

    public const string OBSIDIAN_TEMPLATE = 'Magento_Sales::widget/guest/form.twig';

    public const string ISLAND_TEMPLATE = 'Magento_Sales::widget/guest/island.twig';

    public function __construct(
        private readonly OrdersReturnsForm $ordersReturns
    ) {
    }

    public function beforeToHtml(Template $subject): void
    {
        $template = (string)$subject->getTemplate();
        $position = strpos($template, '::');
        $key = $position === false ? $template : substr($template, $position + 2);
        if ($key !== self::CORE_TEMPLATE) {
            return;
        }

        $subject->setTemplate(self::OBSIDIAN_TEMPLATE);
        if ($subject->getData('island') === null) {
            $subject->setData(
                'island',
                $subject->getLayout()->createBlock(
                    IslandTemplate::class,
                    '',
                    ['data' => ['template' => self::ISLAND_TEMPLATE, 'view' => $this->ordersReturns]]
                )
            );
        }
    }
}
