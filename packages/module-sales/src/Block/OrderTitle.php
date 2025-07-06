<?php
/**
 * This file is part of the MageObsidian - Sales project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Sales\Block;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Document title for the order detail page.
 *
 * The order-view controller does not set the page title (the native layout head
 * does, and the engine suppresses it), so without this the <title> is empty and
 * axe's document-title check fails. This restores it from the current order.
 */
class OrderTitle extends Template
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritDoc
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName) Magento framework hook name.
     */
    protected function _prepareLayout()
    {
        $order = $this->registry->registry('current_order');
        if ($order) {
            $this->pageConfig->getTitle()->set(__('Order # %1', $order->getRealOrderId()));
        }

        return parent::_prepareLayout();
    }
}
