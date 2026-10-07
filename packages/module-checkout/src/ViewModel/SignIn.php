<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Checkout\ViewModel;

use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class SignIn implements ArgumentInterface
{
    public function __construct(
        private readonly UrlInterface $url,
        private readonly EncoderInterface $encoder
    ) {
    }

    public function getUrl(): string
    {
        return $this->url->getUrl('customer/account/login', [
            'referer' => $this->encoder->encode($this->url->getUrl('checkout')),
            'context' => 'checkout',
        ]);
    }
}
