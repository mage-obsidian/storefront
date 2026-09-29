<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Block;

use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use MageObsidian\Storefront\ViewModel\NavigationContinuity;

class NavigationRetention extends AbstractBlock
{
    private const string MODULE_NAME = 'MageObsidian_Storefront';

    private const string SCRIPT_PATH = '/frontend/runtime/navigation-retention.head.js';

    public function __construct(
        Context $context,
        private readonly NavigationContinuity $navigationContinuity,
        private readonly SecureHtmlRenderer $secureRenderer,
        private readonly Reader $moduleReader,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        if (!$this->navigationContinuity->isEnabled()) {
            return '';
        }

        $script = $this->readScript();
        if ($script === '') {
            return '';
        }

        return $this->secureRenderer->renderTag(
            'script',
            ['data-marker' => $this->navigationContinuity->getMarkerId()],
            $script,
            false
        );
    }

    private function readScript(): string
    {
        $path = $this->moduleReader->getModuleDir('view', self::MODULE_NAME) . self::SCRIPT_PATH;
        if (!is_file($path) || !is_readable($path)) {
            return '';
        }

        return (string)file_get_contents($path);
    }
}
