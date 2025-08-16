<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\ViewModel;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

/**
 * Currency switcher data source, consumed from Twig as
 * `block.getSwitcher().getItems()`. Lists the store's allowed currencies and
 * flags the active one.
 *
 * The native `directory/currency/switch` controller is a GET action, so a plain
 * link works — no POST or form key, unlike the legacy Luma template. A `uenc`
 * return param is added so the switch redirects back to the current page.
 * Renders only when more than one currency is allowed.
 */
class CurrencySwitcher implements ArgumentInterface
{
    private const SWITCH_ROUTE = 'directory/currency/switch';

    /**
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $url
     * @param EncoderInterface $urlEncoder
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $url,
        private readonly EncoderInterface $urlEncoder
    ) {
    }

    /**
     * Allowed currencies as switcher items, flagging the active one.
     *
     * @return array<int, array{label: string, url: string, current: bool}>
     */
    public function getItems(): array
    {
        try {
            $store = $this->storeManager->getStore();
            $current = (string)$store->getCurrentCurrencyCode();
            $uenc = $this->urlEncoder->encode($this->url->getCurrentUrl());

            $items = [];
            foreach ($store->getAvailableCurrencyCodes(true) as $code) {
                $items[] = [
                    'label' => (string)$code,
                    'url' => $this->switchUrl((string)$code, $uenc),
                    'current' => $code === $current,
                ];
            }

            return $items;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Code of the active currency (the dropdown trigger).
     *
     * @return string
     */
    public function getCurrentLabel(): string
    {
        try {
            return (string)$this->storeManager->getStore()->getCurrentCurrencyCode();
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Whether there is more than one currency to switch between.
     *
     * @return bool
     */
    public function hasMultiple(): bool
    {
        return count($this->getItems()) > 1;
    }

    /**
     * Build the native GET switch URL for a currency code, with a uenc return.
     *
     * @param string $code
     * @param string $uenc
     * @return string
     */
    private function switchUrl(string $code, string $uenc): string
    {
        return $this->url->getUrl(self::SWITCH_ROUTE, [
            '_query' => [
                'currency' => $code,
                ActionInterface::PARAM_NAME_URL_ENCODED => $uenc,
            ],
        ]);
    }
}
