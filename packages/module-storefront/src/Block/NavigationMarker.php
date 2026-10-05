<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Block;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use MageObsidian\Storefront\ViewModel\NavigationContinuity;

class NavigationMarker extends AbstractBlock
{
    public function __construct(
        Context $context,
        private readonly NavigationContinuity $navigationContinuity,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        if (!$this->navigationContinuity->isEnabled()) {
            return '';
        }

        $id = htmlspecialchars($this->navigationContinuity->getMarkerId(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return "<div id=\"{$id}\" hidden></div>";
    }
}
