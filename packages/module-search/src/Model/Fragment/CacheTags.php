<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Search project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Search\Model\Fragment;

use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\PageCache\Model\Config as PageCacheConfig;
use Magento\PageCache\Model\Spi\PageCacheTagsPreprocessorInterface;

/**
 * Stamps the invalidation tags on a fragment response.
 *
 * Core puts them on in a plugin over Layout::getOutput(), which renders the
 * whole page body — the one call a fragment exists to avoid. Without this the
 * fragments would cache and never invalidate on a product or category save.
 * Tags are collected over every block in the layout, exactly as core does, so a
 * fragment and the page it belongs to are invalidated by the same events.
 */
class CacheTags
{
    private const string HEADER = 'X-Magento-Tags';
    private const string SEPARATOR = ',';

    public function __construct(
        private readonly PageCacheConfig $pageCacheConfig,
        private readonly PageCacheTagsPreprocessorInterface $preprocessor
    ) {
    }

    public function apply(LayoutInterface $layout, HttpResponse $response): void
    {
        if (!$layout->isCacheable() || !$this->pageCacheConfig->isEnabled()) {
            return;
        }

        $isVarnish = $this->pageCacheConfig->getType() === PageCacheConfig::VARNISH;
        $tags = [];

        foreach ($layout->getAllBlocks() as $block) {
            if (!$block instanceof IdentityInterface) {
                continue;
            }
            // Under Varnish an ESI block is fetched — and invalidated — on its own.
            if ($isVarnish && $block->getTtl() > 0) {
                continue;
            }
            $tags[] = $block->getIdentities();
        }

        $tags = $this->preprocessor->process(array_unique(array_merge([], ...$tags)));
        $response->setHeader(self::HEADER, implode(self::SEPARATOR, $tags));
    }
}
