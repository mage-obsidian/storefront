<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Model\Seo;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Storefront\Model\Config\SeoConfig;
use Throwable;

class SocialMetaBuilder
{
    public const string LOCALE_PATH = 'general/locale/code';
    public const string TWITTER_CARD = 'summary_large_image';
    public const string TYPE_PRODUCT = 'product';
    public const string TYPE_WEBSITE = 'website';

    public function __construct(
        private readonly PageConfig $pageConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CanonicalUrl $canonicalUrl,
        private readonly CurrentEntity $currentEntity,
        private readonly StoreMediaUrl $mediaUrl,
        private readonly TextSummarizer $summarizer,
        private readonly ClaimedSocialProperties $claimed,
        private readonly SeoConfig $config
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function build(): array
    {
        if (!$this->config->isSocialMetaEnabled()) {
            return [];
        }

        $title = $this->title();
        $description = $this->description();
        $image = $this->image();
        $twitterSite = $this->config->getTwitterSite();

        $tags = [
            'og:type' => $this->type(),
            'og:site_name' => $this->siteName(),
            'og:locale' => $this->locale(),
            'og:title' => $title,
            'og:description' => $description,
            'og:url' => $this->canonicalUrl->resolve(),
            'og:image' => $image,
            'twitter:card' => self::TWITTER_CARD,
            'twitter:site' => $twitterSite,
            'twitter:title' => $title,
            'twitter:description' => $description,
            'twitter:image' => $image,
        ];

        foreach ($this->claimed->get() as $property) {
            unset($tags[$property]);
        }

        return array_filter($tags, static fn(string $value): bool => $value !== '');
    }

    private function type(): string
    {
        return $this->currentEntity->getProduct() !== null ? self::TYPE_PRODUCT : self::TYPE_WEBSITE;
    }

    private function title(): string
    {
        try {
            $title = trim((string)$this->pageConfig->getTitle()->getShort());
            if ($title === '') {
                $title = trim((string)$this->pageConfig->getTitle()->get());
            }
        } catch (Throwable) {
            return '';
        }

        return $this->summarizer->summarize($title, 0);
    }

    private function description(): string
    {
        try {
            return $this->summarizer->summarize((string)$this->pageConfig->getDescription());
        } catch (Throwable) {
            return '';
        }
    }

    private function image(): string
    {
        $product = $this->currentEntity->getProduct();
        if ($product !== null) {
            $fromProduct = $this->mediaUrl->getProductImage((string)$product->getData('image'));
            if ($fromProduct !== '') {
                return $fromProduct;
            }
        }

        $category = $this->currentEntity->getCategory();
        if ($category !== null && method_exists($category, 'getImageUrl')) {
            try {
                $fromCategory = trim((string)$category->getImageUrl());
            } catch (Throwable) {
                $fromCategory = '';
            }
            if ($fromCategory !== '') {
                return $fromCategory;
            }
        }

        $configured = $this->mediaUrl->forFile(StoreMediaUrl::SOCIAL_DIRECTORY, $this->config->getSocialImage());

        return $configured !== '' ? $configured : $this->mediaUrl->getLogo();
    }

    private function siteName(): string
    {
        try {
            return trim((string)$this->storeManager->getStore()->getFrontendName());
        } catch (Throwable) {
            return '';
        }
    }

    private function locale(): string
    {
        return trim((string)$this->scopeConfig->getValue(self::LOCALE_PATH, ScopeInterface::SCOPE_STORE));
    }
}
