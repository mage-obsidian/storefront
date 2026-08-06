<?php
/**
 * This file is part of the MageObsidian - Review project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Review\Block\Customer;

use Magento\Review\Block\Customer\ListCustomer as CoreListCustomer;

/**
 * Customer review list, uncapped.
 *
 * Core builds a pager in _prepareLayout() and hands it the collection, which
 * page-sizes it to ten. The pager itself is never rendered by Luma's own template
 * either, so anyone with more than ten reviews simply lost the rest.
 *
 * Rendering that pager is not the fix: this is an EAV product collection, and its
 * paging does not survive an offset — asked for page two of twelve it returns no
 * rows at all, while the count still says twelve. Since a customer's own reviews
 * are a naturally small set, the list drops the cap and shows them all.
 */
class ListCustomer extends CoreListCustomer
{
    /**
     * @inheritDoc
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName) Magento framework hook name.
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $reviews = $this->getReviews();
        if ($reviews) {
            $reviews->setPageSize(false);
        }

        return $this;
    }
}
