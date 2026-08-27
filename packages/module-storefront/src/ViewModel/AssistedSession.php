<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\LoginAsCustomerApi\Api\ConfigInterface;
use Throwable;

/**
 * Whether this store lets an administrator browse inside a customer's session.
 *
 * Only the store's own flag, deliberately. The platform's own view model asks a
 * second question — is this visitor being assisted right now — and answering it
 * while the page is being built decides the markup of a page the platform then
 * caches and serves to everybody. So the banner's shell ships on every page and
 * the private content decides whether it is ever shown.
 */
class AssistedSession implements ArgumentInterface
{
    /**
     * @param ConfigInterface $config
     */
    public function __construct(
        private readonly ConfigInterface $config
    ) {
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        try {
            return $this->config->isEnabled();
        } catch (Throwable) {
            return false;
        }
    }
}
