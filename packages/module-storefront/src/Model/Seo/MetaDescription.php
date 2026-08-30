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
use Magento\Framework\DataObject;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Store\Model\ScopeInterface;
use MageObsidian\Storefront\Model\Config\SeoConfig;
use Throwable;

class MetaDescription
{
    public const string DEFAULT_DESCRIPTION_PATH = 'design/head/default_description';
    private const array BODY_FIELDS = ['short_description', 'description'];

    public function __construct(
        private readonly PageConfig $pageConfig,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CurrentEntity $currentEntity,
        private readonly TextSummarizer $summarizer,
        private readonly SeoConfig $config
    ) {
    }

    public function derive(): string
    {
        if (!$this->config->isMetaDescriptionFallbackEnabled()) {
            return '';
        }

        $entity = $this->currentEntity->getProduct() ?? $this->currentEntity->getCategory();
        if ($entity === null) {
            return '';
        }

        $own = $this->summarizer->summarize((string)$entity->getData('meta_description'));
        if ($own !== '') {
            return $own;
        }

        if (!$this->currentIsStoreDefault()) {
            return '';
        }

        return $this->fromBody($entity);
    }

    private function currentIsStoreDefault(): bool
    {
        try {
            $current = $this->summarizer->summarize((string)$this->pageConfig->getDescription(), 0);
        } catch (Throwable) {
            return false;
        }

        if ($current === '') {
            return true;
        }

        $default = $this->summarizer->summarize(
            (string)$this->scopeConfig->getValue(self::DEFAULT_DESCRIPTION_PATH, ScopeInterface::SCOPE_STORE),
            0
        );

        return $default !== '' && $current === $default;
    }

    private function fromBody(DataObject $entity): string
    {
        foreach (self::BODY_FIELDS as $field) {
            $text = $this->summarizer->summarize((string)$entity->getData($field));
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }
}
