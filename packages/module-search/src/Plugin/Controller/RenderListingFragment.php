<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Search project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Search\Plugin\Controller;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\ViewInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\View\Result\Page;
use MageObsidian\Search\Model\Fragment\CacheTags;
use MageObsidian\Search\Model\Fragment\Gate;
use MageObsidian\Search\Model\Fragment\PayloadKey;
use MageObsidian\Search\Model\Fragment\Renderer;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Serves a listing page as fragments instead of a page.
 *
 * The native controller still runs in full: it registers the category, resolves
 * the layer, applies the custom design and builds its layout. What is skipped is
 * the page render — the head, the chrome and the page template. Going through
 * the real controller is what keeps the URLs inside the fragment (filters, sort,
 * pager) pointing at real pages rather than at data endpoints, and what makes
 * the fragment's cacheability the page's own.
 *
 * Two entry points, because the listings do not agree on a controller style: the
 * category page returns a Result\Page, while the search results controller is
 * still the old kind that writes through App\View and returns null.
 */
class RenderListingFragment
{
    private const int STATUS_ERROR = 500;

    /**
     * Layout handle that strips the page furniture. It has to be added before
     * the layout is built — generating a block tree costs real time even when
     * only two of its blocks are ever rendered.
     */
    private const string FRAGMENT_HANDLE = 'mageobsidian_listing_fragment';

    public function __construct(
        private readonly Gate $gate,
        private readonly Renderer $renderer,
        private readonly CacheTags $cacheTags,
        private readonly HttpRequest $request,
        private readonly HttpResponse $response,
        private readonly JsonFactory $jsonFactory,
        private readonly JsonSerializer $jsonSerializer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param callable():(ResultInterface|ResponseInterface|null) $proceed
     */
    public function aroundExecute(
        ActionInterface $subject,
        callable $proceed
    ): ResultInterface|ResponseInterface|null {
        if (!$this->gate->isRequested()) {
            return $proceed();
        }

        $result = $proceed();
        // A forward, a redirect or a 404 is the honest answer to the same URL;
        // the navigator falls back to a full navigation when it sees no JSON.
        if (!$result instanceof Page) {
            return $result;
        }

        try {
            $result->addHandle(self::FRAGMENT_HANDLE);

            return $this->fragment($result->getLayout());
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    /**
     * @param string[]|string|null $handles
     */
    public function beforeLoadLayout(
        ViewInterface $subject,
        $handles = null,
        $generateBlocks = true,
        $generateXml = true,
        $addActionHandles = true
    ): array {
        if ($this->gate->isRequested()) {
            $subject->getLayout()->getUpdate()->addHandle(self::FRAGMENT_HANDLE);
        }

        return [$handles, $generateBlocks, $generateXml, $addActionHandles];
    }

    /**
     * @param callable(string):ViewInterface $proceed
     */
    public function aroundRenderLayout(
        ViewInterface $subject,
        callable $proceed,
        $output = ''
    ): ViewInterface {
        if (!$this->gate->isRequested()) {
            return $proceed($output);
        }

        try {
            $layout = $subject->getLayout();
            $this->respond($this->sections($layout), $layout);
        } catch (Throwable $e) {
            $this->logger->critical($e);
            $this->response->setNoCacheHeaders();
            $this->response->setHttpResponseCode(self::STATUS_ERROR);
            $this->response->representJson(
                $this->jsonSerializer->serialize([PayloadKey::Error->value => true])
            );
        }

        return $subject;
    }

    private function fragment(LayoutInterface $layout): Json
    {
        $sections = $this->sections($layout);
        $this->cacheTags->apply($layout, $this->response);

        $result = $this->jsonFactory->create();
        $result->setData([PayloadKey::Sections->value => $sections]);

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function sections(LayoutInterface $layout): array
    {
        return $this->renderer->render($layout, $this->request->getFullActionName());
    }

    /**
     * @param array<string, string> $sections
     */
    private function respond(array $sections, LayoutInterface $layout): void
    {
        $this->cacheTags->apply($layout, $this->response);
        $this->response->representJson(
            $this->jsonSerializer->serialize([PayloadKey::Sections->value => $sections])
        );
    }

    /**
     * Fails closed and uncached: a poisoned entry would keep the listing broken
     * for the whole TTL, while an error the navigator can see costs one full
     * page load.
     */
    private function failure(Throwable $e): Json
    {
        $this->logger->critical($e);
        // The layout may already have stamped public cache headers on the shared
        // response before it broke; this puts them back to no-store.
        $this->response->setNoCacheHeaders();

        $result = $this->jsonFactory->create();
        $result->setHttpResponseCode(self::STATUS_ERROR);
        $result->setData([PayloadKey::Error->value => true]);

        return $result;
    }
}
