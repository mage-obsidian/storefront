<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Block;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use MageObsidian\Storefront\ViewModel\SpeculationRules as SpeculationRulesViewModel;

/**
 * Emits the speculation ruleset.
 *
 * Renders inline (no .phtml) for the same reason as IslandsRuntime: the module
 * may be symlinked outside the Magento root in dev, which Magento's template
 * path validation rejects. SecureHtmlRenderer is what carries the CSP nonce —
 * `type="speculationrules"` is governed by `script-src`, so without it the
 * browser drops the ruleset wherever the policy is enforced.
 */
class SpeculationRules extends AbstractBlock
{
    /**
     * @param Context $context
     * @param SpeculationRulesViewModel $speculationRules
     * @param SecureHtmlRenderer $secureRenderer
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly SpeculationRulesViewModel $speculationRules,
        private readonly SecureHtmlRenderer $secureRenderer,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml(): string
    {
        if (!$this->speculationRules->isEnabled()) {
            return '';
        }

        return $this->secureRenderer->renderTag(
            'script',
            ['type' => 'speculationrules'],
            $this->speculationRules->getRulesJson(),
            false
        );
    }
}
