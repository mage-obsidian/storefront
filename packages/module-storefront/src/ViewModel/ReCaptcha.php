<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\ViewModel;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\ReCaptchaUi\Model\IsCaptchaEnabledInterface;
use Magento\ReCaptchaUi\Model\UiConfigResolverInterface;
use Throwable;

/**
 * Everything the reCAPTCHA slot needs about one form, read from the platform's
 * own resolvers rather than from configuration paths, so the version a store
 * chose (v2 checkbox, v2 invisible, v3) decides what the browser renders.
 *
 * A form key is the same string Magento uses in `recaptcha_frontend/type_for/*`:
 * customer_login, customer_create, customer_forgot_password, customer_edit,
 * contact, newsletter, review, sendfriend, place_order.
 */
class ReCaptcha implements ArgumentInterface
{
    /**
     * @param UiConfigResolverInterface $uiConfigResolver
     * @param IsCaptchaEnabledInterface $isCaptchaEnabled
     * @param Json $json
     */
    public function __construct(
        private readonly UiConfigResolverInterface $uiConfigResolver,
        private readonly IsCaptchaEnabledInterface $isCaptchaEnabled,
        private readonly Json $json
    ) {
    }

    /**
     * Whether the platform demands a challenge on this form.
     *
     * @param string $formKey
     *
     * @return bool
     */
    public function isEnabledFor(string $formKey): bool
    {
        try {
            return $formKey !== '' && $this->isCaptchaEnabled->isCaptchaEnabledFor($formKey);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Element id the widget is rendered into.
     *
     * @param string $formKey
     *
     * @return string
     */
    public function idFor(string $formKey): string
    {
        return 'recaptcha-' . preg_replace('/[^a-z0-9]+/i', '-', $formKey);
    }

    /**
     * The rendering settings, as the JSON the browser half reads.
     *
     * Empty when the form carries no challenge, so a template can hand the value
     * straight to the attribute without asking twice.
     *
     * @param string $formKey
     *
     * @return string
     */
    public function settingsFor(string $formKey): string
    {
        $settings = $this->settings($formKey);

        return $settings === [] ? '' : $this->json->serialize($settings);
    }

    /**
     * The same settings as an array, for a caller that hands them to an island
     * rather than to an attribute. Empty when the form carries no challenge.
     *
     * @param string $formKey
     *
     * @return array<string, mixed>
     */
    public function configFor(string $formKey): array
    {
        return $this->settings($formKey);
    }

    /**
     * @param string $formKey
     *
     * @return array<string, mixed>
     */
    private function settings(string $formKey): array
    {
        if (!$this->isEnabledFor($formKey)) {
            return [];
        }

        try {
            $config = $this->uiConfigResolver->get($formKey);
        } catch (Throwable) {
            return [];
        }

        $rendering = $config['rendering'] ?? [];
        if (($rendering['sitekey'] ?? '') === '') {
            return [];
        }

        return [
            'formKey' => $formKey,
            'sitekey' => (string)$rendering['sitekey'],
            'size' => (string)($rendering['size'] ?? ''),
            'theme' => (string)($rendering['theme'] ?? ''),
            'badge' => (string)($rendering['badge'] ?? ''),
            'lang' => (string)($rendering['hl'] ?? ''),
            'invisible' => (bool)($config['invisible'] ?? false),
        ];
    }
}
