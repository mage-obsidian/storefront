<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Block\Catalog;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Category heading + document title.
 *
 * MageObsidian suppresses the frontend layout of non-opted-in core modules, so
 * the native page.main.title block (Magento_Theme) and the catalog title wiring
 * never run — leaving category pages without an <h1> or a <title>. This block
 * restores both from the current category: it sets the page-config title in
 * _prepareLayout and exposes the name for the <h1> in its template.
 */
class CategoryTitle extends Template
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
     */
    protected function _prepareLayout()
    {
        $category = $this->registry->registry('current_category');
        if ($category) {
            $title = (string)($category->getMetaTitle() ?: $category->getName());
            if ($title !== '') {
                $this->pageConfig->getTitle()->set($title);
            }
        }

        return parent::_prepareLayout();
    }

    /**
     * Current category name for the heading.
     *
     * @return string
     */
    public function getCategoryName(): string
    {
        $category = $this->registry->registry('current_category');

        return $category ? (string)$category->getName() : '';
    }
}
