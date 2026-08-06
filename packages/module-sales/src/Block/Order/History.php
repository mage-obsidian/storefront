<?php
/**
 * This file is part of the MageObsidian - Sales project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Sales\Block\Order;

use Magento\Sales\Block\Order\History as CoreHistory;
use Magento\Theme\Block\Html\Pager;

/**
 * Order history with a pager the theme can actually render.
 *
 * The core block builds its pager in _prepareLayout() with createBlock(), so a
 * layout <referenceBlock> never reaches it and it keeps Luma's pager.phtml. That
 * mattered more than it looks: the pager also page-sizes the collection to 10,
 * so a customer with more orders than that was silently shown the first ten with
 * no way to reach the rest. Re-templating the block that already exists (rather
 * than declaring a second pager) keeps core's ordering intact — the page size has
 * to be applied before the parent loads the collection.
 */
class History extends CoreHistory
{
    public const string PAGER_TEMPLATE = 'Magento_Theme::html/pager.twig';

    /**
     * @inheritDoc
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName) Magento framework hook name.
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $pager = $this->getChildBlock('pager');
        if ($pager instanceof Pager) {
            $pager->setTemplate(self::PAGER_TEMPLATE);
        }

        return $this;
    }
}
