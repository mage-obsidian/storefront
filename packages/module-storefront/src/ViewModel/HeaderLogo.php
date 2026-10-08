<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\ViewModel;

use Magento\Config\Model\Config\Backend\Image\Logo;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class HeaderLogo implements ArgumentInterface
{
    public function __construct(private readonly UrlInterface $url)
    {
    }

    public function isConfigured(string $logoSource): bool
    {
        if ($logoSource === '') {
            return false;
        }
        $uploads = $this->url->getBaseUrl(['_type' => UrlInterface::URL_TYPE_MEDIA]) . Logo::UPLOAD_DIR . '/';

        return str_starts_with($logoSource, $uploads);
    }
}
