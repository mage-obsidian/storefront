<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Model\Seo;

use Magento\Framework\View\Asset\AssetInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Throwable;

class EmittedCanonical
{
    public const string CONTENT_TYPE = 'canonical';

    public function __construct(
        private readonly PageConfig $pageConfig
    ) {
    }

    public function find(): string
    {
        try {
            $assets = $this->pageConfig->getAssetCollection()->getAll();
        } catch (Throwable) {
            return '';
        }

        foreach ($assets as $asset) {
            if ($asset instanceof AssetInterface && $asset->getContentType() === self::CONTENT_TYPE) {
                return (string)$asset->getUrl();
            }
        }

        return '';
    }
}
