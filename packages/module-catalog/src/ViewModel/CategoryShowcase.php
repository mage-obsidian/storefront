<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Catalog\ViewModel;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Throwable;

/**
 * Maps the current category to a framework "showcase" template id, consumed from
 * Twig as `block.getShowcase().getShowcaseId()`.
 *
 * The demo storefront doubles as the MageObsidian showcase: a couple of
 * top-of-nav PAGE categories carry no products and no CMS landing block, so they
 * would render empty. Instead of off-brand Luma CMS content, those slots host
 * server-rendered marketing sections (features, stack, themes, community). The
 * mapping is by url_key and deliberately scoped to those slots — every other
 * category returns null, so normal listings are untouched.
 */
class CategoryShowcase implements ArgumentInterface
{
    /**
     * url_key => showcase id. The id selects the page template in
     * category/showcase.twig.
     */
    private const SHOWCASE_BY_URL_KEY = [
        'what-is-new' => 'whats-new',
        'sale' => 'community',
    ];

    /**
     * @param Registry $registry
     */
    public function __construct(
        private readonly Registry $registry
    ) {
    }

    /**
     * Showcase id for the current category, or null when it is not a slot.
     *
     * Every non-showcase category returns null so the block renders nothing.
     *
     * @return string|null
     */
    public function getShowcaseId(): ?string
    {
        try {
            $category = $this->registry->registry('current_category');
            if (!$category instanceof CategoryInterface) {
                return null;
            }
            $urlKey = (string)$category->getUrlKey();
            return self::SHOWCASE_BY_URL_KEY[$urlKey] ?? null;
        } catch (Throwable) {
            return null;
        }
    }
}
