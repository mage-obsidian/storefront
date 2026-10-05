<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Checkout\Model\Shell;

use Magento\Framework\App\Request\Http as HttpRequest;
use MageObsidian\ModernFrontend\Model\Config\ConfigProvider;

/**
 * The single answer to "is this checkout being served as a cacheable shell?".
 *
 * Two kill switches meet here, as in the listing fragments: an admin flag and a
 * per-request query parameter. Everything downstream reads it, so the paths can
 * never disagree about which one is running.
 */
class Gate
{
    /** Only the literal off value counts, so appended junk cannot bypass. */
    public const string BYPASS_PARAM = 'obsidian_shell';
    private const string BYPASS_VALUE_OFF = '0';

    /**
     * @param ConfigProvider $configProvider
     * @param HttpRequest $request
     */
    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly HttpRequest $request
    ) {
    }

    /**
     * @return bool
     */
    public function isCacheable(): bool
    {
        if ((string)$this->request->getParam(self::BYPASS_PARAM) === self::BYPASS_VALUE_OFF) {
            return false;
        }

        return $this->configProvider->isCheckoutShellCacheable();
    }
}
