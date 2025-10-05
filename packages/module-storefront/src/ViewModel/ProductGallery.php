<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Throwable;

/**
 * Media-gallery data for the product detail page, consumed from Twig as
 * `block.getGallery().getImages()`.
 *
 * The core catalog gallery is suppressed with the rest of the module layout, so
 * the PDP renders its own server-side gallery (LCP-friendly, indexable, lazy
 * thumbs). This ViewModel turns the product's media gallery into a flat list of
 * tiles — a large display URL, a thumb URL and the original full-size URL per
 * image — sized through the theme view.xml ids. When a product has no gallery,
 * it degrades to a single base-image tile (the image helper yields the
 * placeholder if even that is missing) so the template always has something to
 * show. The per-variant image swap on configurables is driven client-side from
 * the configurable island; this only owns the initial, crawlable set.
 */
class ProductGallery implements ArgumentInterface
{
    /**
     * Theme view.xml image ids for each gallery role.
     */
    private const LARGE_ID = 'product_page_image_large';
    private const THUMB_ID = 'product_page_image_small';

    /**
     * @param Registry $registry
     * @param ImageHelper $imageHelper
     */
    public function __construct(
        private readonly Registry $registry,
        private readonly ImageHelper $imageHelper
    ) {
    }

    /**
     * Gallery tiles for the current product.
     *
     * @return array<int, array{large: string, thumb: string, full: string, label: string, isMain: bool}>
     */
    public function getImages(): array
    {
        try {
            $product = $this->registry->registry('current_product');
            if (!$product instanceof ProductInterface) {
                return [];
            }

            $images = $this->fromMediaGallery($product);

            return $images === [] ? $this->fromBaseImage($product) : $images;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Build a tile per visible media-gallery image.
     *
     * @param ProductInterface $product
     * @return array<int, array{large: string, thumb: string, full: string, label: string, isMain: bool}>
     */
    private function fromMediaGallery(ProductInterface $product): array
    {
        $gallery = $product->getMediaGalleryImages();
        if ($gallery === null) {
            return [];
        }

        $baseFile = (string)$product->getData('image');
        $tiles = [];
        foreach ($gallery as $image) {
            if ((string)$image->getData('media_type') !== 'image' || (int)$image->getData('disabled') === 1) {
                continue;
            }
            $file = (string)$image->getData('file');
            $tiles[] = [
                'large' => $this->scaled($product, self::LARGE_ID, $file),
                'thumb' => $this->scaled($product, self::THUMB_ID, $file),
                'full' => (string)$image->getData('url'),
                'label' => (string)($image->getData('label') ?: $product->getName()),
                'isMain' => $file === $baseFile,
            ];
        }

        return $tiles;
    }

    /**
     * Single tile from the base image (or placeholder) when there is no gallery.
     *
     * @param ProductInterface $product
     * @return array<int, array{large: string, thumb: string, full: string, label: string, isMain: bool}>
     */
    private function fromBaseImage(ProductInterface $product): array
    {
        $large = (string)$this->imageHelper->init($product, self::LARGE_ID)->getUrl();
        if ($large === '') {
            return [];
        }

        return [[
            'large' => $large,
            'thumb' => (string)$this->imageHelper->init($product, self::THUMB_ID)->getUrl(),
            'full' => $large,
            'label' => (string)$product->getName(),
            'isMain' => true,
        ]];
    }

    /**
     * Resolve a sized URL for one gallery file under the given view.xml id.
     *
     * @param ProductInterface $product
     * @param string $imageId
     * @param string $file
     * @return string
     */
    private function scaled(ProductInterface $product, string $imageId, string $file): string
    {
        return (string)$this->imageHelper->init($product, $imageId)->setImageFile($file)->getUrl();
    }
}
