<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Plugin\PageBuilder;

use Magento\Framework\Filter\Template;
use MageObsidian\Storefront\Model\PageBuilder\Enhancer;
use MageObsidian\Storefront\Model\PageBuilder\StyleBlock;

/**
 * Hooks the filter every piece of authored content passes through — a CMS page,
 * a CMS block, a product or category description, a widget — rather than any one
 * of their blocks, so Page Builder content is treated the same wherever a
 * merchant put it.
 *
 * Sorted after Page Builder's own plugin on the same method: it is the one that
 * produces the markup this rewrites. Two things happen here — the author's
 * stylesheet is handed to the platform so the policy does not drop it, and the
 * behaviour the interactive content types need is pulled in beside them.
 */
class SecureAuthoredStyles
{
    /**
     * @param StyleBlock $styleBlock
     * @param Enhancer $enhancer
     */
    public function __construct(
        private readonly StyleBlock $styleBlock,
        private readonly Enhancer $enhancer
    ) {
    }

    /**
     * @param Template $subject
     * @param string $result
     *
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterFilter(Template $subject, $result): string
    {
        return $this->enhancer->enhance($this->styleBlock->rewrite((string)$result));
    }
}
