<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Block;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;

class NavigationProgress extends AbstractBlock
{
    private const string ASSET = 'MageObsidian_Storefront::js/navigationProgress';

    public function __construct(
        Context $context,
        private readonly ViteResolver $viteResolver,
        private readonly SecureHtmlRenderer $secureRenderer,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

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
