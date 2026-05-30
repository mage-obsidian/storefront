<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\ViewModel;

use Magento\Cookie\Helper\Cookie as CookieHelper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Throwable;

/**
 * Cookie-restriction (e-Privacy) consent banner data source, consumed from Twig
 * as `block.getCookieNotice()`. Renders only when cookie restriction mode is on
 * (`web/cookie/cookie_restriction`); the banner is then progressively shown by
 * the cookie-notice enhancer, which sets the `user_allowed_save_cookie` cookie
 * with the accepted website ids on consent — the same contract Magento's server
 * read-path (`Cookie::isUserNotAllowSaveCookie`) expects.
 */
class CookieNotice implements ArgumentInterface
{
    public const COOKIE_NAME = CookieHelper::IS_USER_ALLOWED_SAVE_COOKIE;

    private const PRIVACY_ROUTE = 'privacy-policy-cookie-restriction-mode';
    private const NO_COOKIES_ROUTE = 'cookie/index/noCookies';

    /**
     * @param CookieHelper $cookieHelper
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly CookieHelper $cookieHelper,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * Whether the consent banner should render at all.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        try {
            return (bool)$this->cookieHelper->isCookieRestrictionModeEnabled();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * JSON map of accepted-save-cookie website ids.
     *
     * This is the value the consent cookie is set to (e.g. `{"1":1}`).
     *
     * @return string
     */
    public function getWebsiteIdsJson(): string
    {
        try {
            return (string)$this->cookieHelper->getAcceptedSaveCookiesWebsiteIds();
        } catch (Throwable) {
            return '{}';
        }
    }

    /**
     * Consent cookie lifetime in seconds.
     *
     * @return int
     */
    public function getLifetime(): int
    {
        try {
            return (int)$this->cookieHelper->getCookieRestrictionLifetime();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Name of the consent cookie.
     *
     * @return string
     */
    public function getCookieName(): string
    {
        return self::COOKIE_NAME;
    }

    /**
     * "Learn more" privacy policy URL.
     *
     * @return string
     */
    public function getPrivacyUrl(): string
    {
        return $this->url->getUrl(self::PRIVACY_ROUTE);
    }

    /**
     * Fallback URL when the browser refuses to store the consent cookie.
     *
     * @return string
     */
    public function getNoCookiesUrl(): string
    {
        return $this->url->getUrl(self::NO_COOKIES_ROUTE);
    }
}
