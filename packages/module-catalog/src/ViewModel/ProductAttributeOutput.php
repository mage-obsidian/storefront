<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Catalog\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Output as OutputHelper;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Routes a product attribute value through the same output filter core applies.
 *
 * The helper escapes every attribute that is not marked HTML-allowed, converts a
 * textarea's newlines and resolves CMS directives inside a WYSIWYG attribute, so
 * printing a value without it is both less strict than the platform and unable to
 * render {{media url=...}}. A non-string value follows core's second branch and
 * comes back escaped.
 */
class ProductAttributeOutput implements ArgumentInterface
{
    /**
     * @param OutputHelper $outputHelper
     * @param Escaper $escaper
     */
    public function __construct(
        private readonly OutputHelper $outputHelper,
        private readonly Escaper $escaper
    ) {
    }

    /**
     * Filtered attribute value, safe to print raw.
     *
     * @param ProductInterface $product
     * @param mixed $value
     * @param string $attributeCode
     *
     * @return string
     */
    public function filter(ProductInterface $product, mixed $value, string $attributeCode): string
    {
        $html = (string)$this->outputHelper->productAttribute($product, $value, $attributeCode);

        return is_string($value) ? $html : $this->escaper->escapeHtml($html);
    }
}
