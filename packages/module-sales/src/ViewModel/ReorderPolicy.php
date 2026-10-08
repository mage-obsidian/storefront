<?php
/**
 * This file is part of the MageObsidian - Sales project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Sales\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Helper\Reorder;

class ReorderPolicy implements ArgumentInterface
{
    public function __construct(private readonly Reorder $reorder)
    {
    }

    public function canReorder(?OrderInterface $order): bool
    {
        if ($order === null || (int)$order->getEntityId() === 0) {
            return false;
        }

        return $this->reorder->canReorder((int)$order->getEntityId());
    }
}
