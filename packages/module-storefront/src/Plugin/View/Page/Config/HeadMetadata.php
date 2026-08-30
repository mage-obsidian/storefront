<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Plugin\View\Page\Config;

use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Config\Renderer;
use MageObsidian\Storefront\Model\Config\SeoConfig;
use MageObsidian\Storefront\Model\Seo\MetaDescription;
use MageObsidian\Storefront\Model\Seo\RobotsDirectives;
use MageObsidian\Storefront\Model\Seo\SocialMetaBuilder;

class HeadMetadata
{
    public function __construct(
        private readonly PageConfig $pageConfig,
        private readonly MetaDescription $metaDescription,
        private readonly RobotsDirectives $robotsDirectives,
        private readonly SocialMetaBuilder $socialMetaBuilder,
        private readonly SeoConfig $config
    ) {
    }

    public function beforeRenderMetadata(Renderer $subject): void
    {
        $description = $this->metaDescription->derive();
        if ($description !== '') {
            $this->pageConfig->setDescription($description);
        }

        $robots = $this->robotsDirectives->extend((string)$this->pageConfig->getRobots());
        if ($robots !== '') {
            $this->pageConfig->setRobots($robots);
        }

        if (!$this->config->isSocialMetaEnabled()) {
            return;
        }

        $existing = $this->pageConfig->getMetadata();
        foreach ($this->socialMetaBuilder->build() as $name => $content) {
            if (!isset($existing[$name])) {
                $this->pageConfig->setMetadata($name, $content);
            }
        }
    }
}
