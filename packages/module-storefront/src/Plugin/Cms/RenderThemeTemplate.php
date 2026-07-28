<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Plugin\Cms;

use Magento\Cms\Block\Page as CmsPage;
use MageObsidian\ModernFrontend\Block\Template;

/**
 * Lets a theme render a CMS page from a template instead of the stored content.
 *
 * A page whose text belongs to the codebase — a privacy notice, a shipping
 * policy — should ship and be translated with the theme, not live in a database
 * row that no install reproduces. The theme opts a page in through the layout
 * handle Magento already builds for it:
 *
 *     <!-- Magento_Cms/layout/cms_page_view_id_<identifier>.xml -->
 *     <referenceBlock name="cms_page">
 *         <arguments>
 *             <argument name="obsidian_template" xsi:type="string">Vendor_Module::path.twig</argument>
 *         </arguments>
 *     </referenceBlock>
 *
 * The native block stays in the layout, so the document title, meta, keywords,
 * breadcrumbs and body class still come from the CMS record and a merchant can
 * take the page back by deleting the layout file.
 */
class RenderThemeTemplate
{
    public const string TEMPLATE_ARGUMENT = 'obsidian_template';

    /**
     * @param CmsPage $subject
     * @param string $result
     *
     * @return string
     */
    public function afterToHtml(CmsPage $subject, $result): string
    {
        $template = $subject->getData(self::TEMPLATE_ARGUMENT);
        if (!is_string($template) || $template === '') {
            return (string)$result;
        }

        return $subject->getLayout()
            ->createBlock(Template::class)
            ->setTemplate($template)
            ->toHtml();
    }
}
