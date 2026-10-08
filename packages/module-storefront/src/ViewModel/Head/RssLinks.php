<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\ViewModel\Head;

use Magento\Framework\App\Rss\RssManagerInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class RssLinks implements ArgumentInterface
{
    public function __construct(private readonly RssManagerInterface $rssManager)
    {
    }

    public function all(): array
    {
        $links = [];
        foreach ($this->rssManager->getProviders() as $provider) {
            $feed = $provider->getFeeds();
            if (empty($feed)) {
                continue;
            }
            foreach (isset($feed['group']) ? $feed['feeds'] : [$feed] as $item) {
                $links[] = ['label' => (string)$item['label'], 'href' => (string)$item['link']];
            }
        }

        return $links;
    }
}
