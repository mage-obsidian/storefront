<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\ViewModel;

use Magento\Cookie\Helper\Cookie as CookieHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\GoogleAdwords\Helper\Data;
use Magento\Store\Model\StoreManagerInterface;

class LegacyGoogleAdsConversion implements ArgumentInterface
{
    public function __construct(
        private readonly Data $helper,
        private readonly CookieHelper $cookies,
        private readonly StoreManagerInterface $stores
    ) {
    }

    public function conversion(): array
    {
        if (!$this->helper->isGoogleAdwordsActive()) {
            return [];
        }

        $variables = [
            'google_conversion_id' => $this->helper->getConversionId(),
            'google_conversion_language' => $this->helper->getConversionLanguage(),
            'google_conversion_format' => $this->helper->getConversionFormat(),
            'google_conversion_color' => $this->helper->getConversionColor(),
            'google_conversion_label' => $this->helper->getConversionLabel(),
            'google_conversion_value' => $this->helper->getConversionValue(),
        ];
        if ($this->helper->hasSendConversionValueCurrency() && $this->helper->getConversionValueCurrency()) {
            $variables['google_conversion_currency'] = $this->helper->getConversionValueCurrency();
        }

        return [
            'isCookieRestrictionModeEnabled' => (bool)$this->cookies->isCookieRestrictionModeEnabled(),
            'currentWebsite' => (int)$this->stores->getWebsite()->getId(),
            'cookieName' => CookieHelper::IS_USER_ALLOWED_SAVE_COOKIE,
            'src' => $this->helper->getConversionJsSrc(),
            'variables' => $variables,
        ];
    }

    public function pixelUrl(): string
    {
        if (!$this->helper->isGoogleAdwordsActive() || $this->cookies->isUserNotAllowSaveCookie()) {
            return '';
        }

        return $this->helper->getConversionImgSrc();
    }
}
