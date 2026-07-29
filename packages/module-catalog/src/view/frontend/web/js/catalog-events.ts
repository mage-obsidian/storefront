export const CatalogEvent = {
    ProductVariantChange: "product_variant_change",
    BundleSelectionChange: "bundle_selection_change",
    ProductGalleryChange: "product_gallery_change",
} as const;

export type CatalogEvent = (typeof CatalogEvent)[keyof typeof CatalogEvent];

/** Kept so inline snippets written against the old CustomEvent keep working. */
export const LEGACY_VARIANT_IMAGE_EVENT = "obsidian:variant-image";

export interface ProductVariantChangeEvent {
    productId: number | null;
}

export interface BundleSelectionChangeEvent {
    selections: unknown;
}

export interface GalleryTile {
    large: string;
    thumb: string;
    label: string;
}

export interface ProductGalleryChangeEvent {
    reset?: boolean;
    large?: string;
    label?: string;
    tiles?: GalleryTile[];
}

declare module "mage-obsidian/runtime/eventManager.ts" {
    interface StorefrontEventMap {
        [CatalogEvent.ProductVariantChange]: ProductVariantChangeEvent;
        [CatalogEvent.BundleSelectionChange]: BundleSelectionChangeEvent;
        [CatalogEvent.ProductGalleryChange]: ProductGalleryChangeEvent;
    }
}
