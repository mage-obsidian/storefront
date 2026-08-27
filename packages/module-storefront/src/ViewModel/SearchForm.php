<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\ViewModel;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Search\Helper\Data as SearchHelper;
use Magento\Search\Model\QueryFactory;
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
     * @param QueryFactory $queryFactory
     * @param RequestInterface $request
     */
    public function __construct(
        private readonly SearchHelper $searchHelper,
        private readonly ConfigProvider $configProvider,
        private readonly QueryFactory $queryFactory,
        private readonly RequestInterface $request
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
     * Current query text, as the shopper typed it (empty off the result page).
     *
     * The value is returned unescaped on purpose: every consumer escapes it once
     * — Twig autoescapes the input's value attribute, and the island's props are
     * JSON-encoded. Returning the helper's pre-escaped text made an ampersand
     * come back as &amp; in the box, the breadcrumb and the island. The query
     * model carries the same prepared, length-capped text the helper escapes, so
     * it is read from there rather than re-deriving core's preparation, and only
     * when the request actually carries a query.
     *
     * @return string
     */
    public function getQueryValue(): string
    {
        $queryText = $this->request->getParam($this->getQueryParam());
        if ($queryText === null || is_array($queryText) || trim((string)$queryText) === '') {
            return '';
        }

        return (string)$this->queryFactory->get()->getQueryText();
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
