<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Search\Helper\Data as SearchHelper;
use Magento\Search\ViewModel\ConfigProvider;

/**
 * Header quick-search data, consumed from Twig as `block.getSearchForm()`.
 *
 * Wraps Magento's native search helper and config provider (the same backend
 * Luma's mini form uses) so the OBSIDIAN search box and autocomplete island read
 * store-aware URLs and the query-length limits from one place. Reading core here
 * is allowed for the foundation, like Navigation reads core categories.
 */
class SearchForm implements ArgumentInterface
{
    /**
     * @param SearchHelper $searchHelper
     * @param ConfigProvider $configProvider
     */
    public function __construct(
        private readonly SearchHelper $searchHelper,
        private readonly ConfigProvider $configProvider
    ) {
    }

    /**
     * Result page URL the form submits to (GET).
     *
     * @return string
     */
    public function getActionUrl(): string
    {
        return $this->searchHelper->getResultUrl();
    }

    /**
     * Query string parameter name (native default: "q").
     *
     * @return string
     */
    public function getQueryParam(): string
    {
        return $this->searchHelper->getQueryParamName();
    }

    /**
     * Current, escaped query text (empty off the result page).
     *
     * @return string
     */
    public function getQueryValue(): string
    {
        return (string)$this->searchHelper->getEscapedQueryText();
    }

    /**
     * Autocomplete suggestions endpoint (search/ajax/suggest).
     *
     * @return string
     */
    public function getSuggestUrl(): string
    {
        return $this->searchHelper->getSuggestUrl();
    }

    /**
     * Minimum query length before searching/suggesting.
     *
     * @return int
     */
    public function getMinQueryLength(): int
    {
        return (int)$this->searchHelper->getMinQueryLength();
    }

    /**
     * Maximum query length accepted by the input.
     *
     * @return int
     */
    public function getMaxQueryLength(): int
    {
        return (int)$this->searchHelper->getMaxQueryLength();
    }

    /**
     * Whether the store enables the autocomplete suggestions.
     *
     * @return bool
     */
    public function isSuggestionsAllowed(): bool
    {
        return $this->configProvider->isSuggestionsAllowed();
    }
}
