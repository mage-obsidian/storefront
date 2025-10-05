<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Block\Catalog;

use Magento\Catalog\Helper\Data as CatalogData;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Block\Html\Breadcrumbs as ThemeBreadcrumbs;

/**
 * Catalog breadcrumb trail for category/product pages.
 *
 * Magento_Theme ships an empty `breadcrumbs` block, but the populator that fills
 * it (Magento\Catalog\Block\Breadcrumbs) lives in the suppressed core catalog
 * layout — so without this the trail is always blank. We fold the populate +
 * render into one self-contained block: it seeds Home and the category/product
 * path from the catalog helper in _prepareLayout, then renders the trail itself
 * (breadcrumbs.twig). The JSON-LD BreadcrumbList is emitted independently by the
 * engine, so the template stays presentation-only.
 */
class Breadcrumbs extends ThemeBreadcrumbs
{
    /**
     * @param Context $context
     * @param CatalogData $catalogData
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly CatalogData $catalogData,
        private readonly StoreManagerInterface $storeManager,
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
        $path = $this->catalogData->getBreadcrumbPath();
        if ($path !== []) {
            $this->addCrumb('home', [
                'label' => __('Home'),
                'title' => __('Go to Home Page'),
                'link' => $this->storeManager->getStore()->getBaseUrl(),
            ]);
            foreach ($path as $name => $breadcrumb) {
                $this->addCrumb($name, $breadcrumb);
            }
        }

        return parent::_prepareLayout();
    }
}
