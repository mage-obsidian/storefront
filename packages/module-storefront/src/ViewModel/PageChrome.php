<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\ViewModel;

use Magento\Framework\App\Config\Initial;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

class PageChrome implements ArgumentInterface
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Initial $initial
    ) {
    }

    public function getWelcome(): string
    {
        $message = $this->configured('design/header/welcome');

        return $message === '' ? '' : (string)__($message);
    }

    public function hasCopyright(): bool
    {
        return $this->configured('design/footer/copyright') !== '';
    }

    private function configured(string $path): string
    {
        $value = trim((string)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE));

        return $value === trim((string)$this->shipped($path)) ? '' : $value;
    }

    private function shipped(string $path): mixed
    {
        $node = $this->initial->getData('default');
        foreach (explode('/', $path) as $key) {
            if (!is_array($node)) {
                return null;
            }
            $node = $node[$key] ?? null;
        }

        return $node;
    }
}
