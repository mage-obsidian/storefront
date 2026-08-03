<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Search project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Search\ViewModel;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use MageObsidian\Search\Model\Config;
use MageObsidian\Search\Model\Fragment\RequestParameter;
use MageObsidian\Search\Model\Fragment\SectionPool;

/**
 * Feeds the listing navigator the two names it shares with the PHP side — the
 * query parameter and the section attribute — so neither is duplicated as a
 * literal in the client bundle.
 */
class ListingNavigation implements ArgumentInterface
{
    public const string SECTION_ATTRIBUTE = 'data-obsidian-section';

    private const string CURRENT_ACTION = '*/*/*';

    public function __construct(
        private readonly Config $config,
        private readonly SectionPool $sectionPool,
        private readonly HttpRequest $request,
        private readonly UrlInterface $url
    ) {
    }

    public function isEnabled(): bool
    {
        if (!$this->config->areFragmentsEnabled()) {
            return false;
        }

        if ((string)$this->request->getParam(RequestParameter::OptOut->value, '') === RequestParameter::VALUE_OFF) {
            return false;
        }

        return $this->sectionPool->isServable($this->request->getFullActionName());
    }

    public function getFragmentParameter(): string
    {
        return RequestParameter::Fragment->value;
    }

    public function getSectionAttribute(): string
    {
        return self::SECTION_ATTRIBUTE;
    }

    /**
     * The paths a listing control can point at, so the navigator can tell one
     * from a product card without guessing.
     *
     * Built with the very call the filters, the sorter and the pager use, which
     * is the only reliable answer: on a category it resolves to the rewritten
     * URL, while on the search results page it resolves to
     * /catalogsearch/result/index/ — a path the page itself is not served from.
     *
     * @return string[]
     */
    public function getListingPaths(): array
    {
        $generated = $this->url->getUrl(self::CURRENT_ACTION, [
            '_current' => true,
            '_use_rewrite' => true,
            '_query' => [],
        ]);

        return array_values(array_unique(array_filter([
            (string)parse_url($generated, PHP_URL_PATH),
            (string)parse_url($this->request->getRequestUri(), PHP_URL_PATH),
        ])));
    }
}
