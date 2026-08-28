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
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;

class SessionMessages extends AbstractBlock
{
    private const string ASSET = 'MageObsidian_Storefront::js/session-messages';

    /**
     * @param Context $context
     * @param ViteResolver $viteResolver
     * @param SecureHtmlRenderer $secureRenderer
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly ViteResolver $viteResolver,
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
        return $this->secureRenderer->renderTag(
            'script',
            [
                'type' => 'module',
                'src' => $this->viteResolver->getViteFileUrl(self::ASSET),
                'fetchpriority' => 'low',
            ],
            '',
            false
        );
    }
}
