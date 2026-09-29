<?php
/**
 * This file is part of the MageObsidian - SendFriend project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace MageObsidian\SendFriend\ViewModel;

use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\SendFriend\Helper\Data as SendFriendHelper;

/**
 * "Email to a Friend" availability + link for the PDP.
 *
 * Injected into the product-view block so Magento_Catalog stays unaware of the
 * feature. `isAllowed()` mirrors the native enable flag; `getSendUrl()` targets
 * the share form for the current product.
 */
class SendToFriend implements ArgumentInterface
{
    /**
     * @param SendFriendHelper $sendFriendHelper
     * @param Registry $registry
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly SendFriendHelper $sendFriendHelper,
        private readonly Registry $registry,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * Whether the email-to-a-friend feature is enabled.
     *
     * @return bool
     */
    public function isAllowed(): bool
    {
        return (bool)$this->sendFriendHelper->isEnabled();
    }

    /**
     * Share-form URL for the current product (empty off a product page).
     *
     * @return string
     */
    public function getSendUrl(): string
    {
        $product = $this->registry->registry('current_product');
        if (!$product || !$product->getId()) {
            return '';
        }

        return $this->url->getUrl('sendfriend/product/send', ['id' => $product->getId()]);
    }
}
