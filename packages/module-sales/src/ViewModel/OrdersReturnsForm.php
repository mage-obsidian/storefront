<?php
/**
 * This file is part of the MageObsidian - Sales project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Sales\ViewModel;

use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Backs the guest "Orders and Returns" lookup island. The form key is primed
 * server-side and passed as a prop because the island POSTs straight to the
 * (non-CSRF-aware) sales/guest/view controller, which validates it — the legacy
 * jQuery widget injected the key from a cookie; here it is rendered explicitly.
 */
class OrdersReturnsForm implements ArgumentInterface
{
    /**
     * @param UrlInterface $url
     * @param FormKey $formKey
     */
    public function __construct(
        private readonly UrlInterface $url,
        private readonly FormKey $formKey
    ) {
    }

    /**
     * URL the guest lookup form POSTs to.
     */
    public function getActionUrl(): string
    {
        return $this->url->getUrl('sales/guest/view');
    }

    /**
     * Server-primed form key the island submits for the controller's CSRF check.
     */
    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }
}
