<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Block;

use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;

/**
 * Loads the form-key provider on every page.
 *
 * Full-page cache bakes a form key into the HTML, so by the time a visitor gets
 * that page the value is a stranger's and every native POST is rejected — with a
 * redirect back to the form and no message. The provider syncs the key from the
 * cookie; it only had to be loaded, and until now it rode in as a transitive
 * import of the cart, wishlist, compare and auth entries, which left every page
 * without one of those islands broken.
 *
 * Renders inline (no .phtml) for the same reason as IslandsRuntime: the module
 * may be symlinked outside the Magento root in dev, which Magento's template
 * path validation rejects. It goes through SecureHtmlRenderer so the script's
 * origin is registered with the CSP policy — for a tag carrying `src` that means
 * a FetchPolicy for the host, not a nonce, which only inline content gets.
 */
class FormKeyProvider extends AbstractBlock
{
    private const string ASSET = 'MageObsidian_Storefront::js/form-key-provider';

    /**
     * Magento\PageCache\Model\Cache\Type::TYPE_IDENTIFIER, inlined so the module
     * does not have to depend on magento/module-page-cache for one string. This
     * is the same flag Magento\PageCache\Model\Config::isEnabled() reads.
     */
    private const string FULL_PAGE_CACHE = 'full_page';

    /**
     * @param Context $context
     * @param ViteResolver $viteResolver
     * @param SecureHtmlRenderer $secureRenderer
     * @param StateInterface $cacheState
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly ViteResolver $viteResolver,
        private readonly SecureHtmlRenderer $secureRenderer,
        private readonly StateInterface $cacheState,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml(): string
    {
        // Without full-page cache the rendered key is the session's own, so the
        // provider has nothing to correct and the page should not pay for it.
        if (!$this->cacheState->isEnabled(self::FULL_PAGE_CACHE)) {
            return '';
        }

        return $this->secureRenderer->renderTag(
            'script',
            ['type' => 'module', 'src' => $this->viteResolver->getViteFileUrl(self::ASSET)],
            '',
            false
        );
    }
}
