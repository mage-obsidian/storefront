<?php
/**
 * This file is part of the MageObsidian - Customer project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Customer\ViewModel;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class CheckoutContext implements ArgumentInterface
{
    private const string CONTEXT = 'checkout';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly UrlInterface $url
    ) {
    }

    public function isActive(): bool
    {
        return $this->request->getParam('context') === self::CONTEXT;
    }

    public function getReferer(): string
    {
        return (string)$this->request->getParam('referer', '');
    }

    public function urlTo(string $route): string
    {
        if (!$this->isActive()) {
            return $this->url->getUrl($route);
        }
        $query = ['context' => self::CONTEXT];
        $referer = $this->getReferer();
        if ($referer !== '') {
            $query['referer'] = $referer;
        }

        return $this->url->getUrl($route, $query);
    }
}
