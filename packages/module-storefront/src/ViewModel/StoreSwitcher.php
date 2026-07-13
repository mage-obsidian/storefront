<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\ViewModel\SwitcherUrlProvider;
use Throwable;

/**
 * Language/store switcher data source, consumed from Twig as
 * `block.getSwitcher().getItems()` (registered as a layout `<argument>`). The
 * header dropdown and the mobile menu both read it, so the switcher lives in one
 * place.
 *
 * Reuses Magento's own SwitcherUrlProvider for the redirect URL, so the target
 * store is reached exactly as the native switcher would (same `___store` /
 * `___from_store` / `uenc` handling). Renders only when there is more than one
 * active store view; any failure degrades to an empty list (no switcher shown).
 */
class StoreSwitcher implements ArgumentInterface
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param SwitcherUrlProvider $urlProvider
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly SwitcherUrlProvider $urlProvider
    ) {
    }

    /**
     * Active store views as switcher items, flagging the current one.
     *
     * Scoped to the current store group, matching Magento's native switcher
     * (Magento\Store\Block\Switcher::getStores()): a language/store-view switch
     * stays within the current group, so views from other groups/websites are
     * not offered.
     *
     * @return array<int, array{label: string, url: string, current: bool}>
     */
    public function getItems(): array
    {
        try {
            $currentStore = $this->storeManager->getStore();
            $currentId = (int)$currentStore->getId();
            $currentGroupId = (int)$currentStore->getGroupId();
            $items = [];
            foreach ($this->storeManager->getStores() as $store) {
                if (!$store->isActive() || (int)$store->getGroupId() !== $currentGroupId) {
                    continue;
                }
                $items[] = [
                    'label' => (string)$store->getName(),
                    'url' => (string)$this->urlProvider->getTargetStoreRedirectUrl($store),
                    'current' => (int)$store->getId() === $currentId,
                ];
            }

            return $items;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Label of the current store view (the dropdown trigger).
     *
     * @return string
     */
    public function getCurrentLabel(): string
    {
        try {
            return (string)$this->storeManager->getStore()->getName();
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Whether there is more than one store view to switch between.
     *
     * @return bool
     */
    public function hasMultiple(): bool
    {
        return count($this->getItems()) > 1;
    }
}
