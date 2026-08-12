/**
 * Product gallery enhancer. The gallery is server-rendered (LCP-friendly,
 * crawlable); this only adds interactivity: clicking a thumb swaps the main
 * image, and the strip listens for `product_gallery_change` so the configurable
 * island can drive it when a variant is chosen — swapping the hero, rebuilding
 * the whole thumbnail strip from the variant's media, or resetting back to the
 * base product. Image swaps use the View Transitions API for a crossfade,
 * disabled under prefers-reduced-motion. Listeners are delegated on the strip
 * container so rebuilt thumbs stay interactive without re-binding.
 */
import events from "MageObsidian_ModernFrontend::js/events";
import {
    CatalogEvent,
    LEGACY_VARIANT_IMAGE_EVENT,
    type GalleryTile,
    type ProductGalleryChangeEvent,
} from "MageObsidian_Catalog::js/catalog-events";

const SWAP_SCOPE_CLASS = "pdp-gallery-swap";
const DECODE_BUDGET = 400;

const prefersReducedMotion = (): boolean =>
    typeof window.matchMedia === "function" &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;

const decoded = (sources: (string | null | undefined)[]): Promise<unknown> =>
    Promise.all(
        sources.filter(Boolean).map((src) => {
            const image = new Image();
            image.src = src as string;
            return typeof image.decode === "function"
                ? image.decode().catch(() => {})
                : Promise.resolve();
        }),
    );

const within = (promise: Promise<unknown>, ms: number): Promise<unknown> =>
    Promise.race([promise, new Promise((resolve) => setTimeout(resolve, ms))]);

function init(): void {
    const root = document.querySelector<HTMLElement>("[data-pdp]");
    if (!root) {
        return;
    }
    const main = root.querySelector<HTMLImageElement>("[data-gallery-main]");
    if (!main) {
        return;
    }
    const strip = root.querySelector<HTMLElement>("[data-gallery-thumbs]");

    // Snapshot the base product's gallery so a variant reset can restore it.
    const base = {
        thumbs: strip ? strip.innerHTML : null,
        src: main.getAttribute("src"),
        label: main.getAttribute("alt"),
    };
    const labelPattern = strip?.dataset.thumbLabel ?? "Show image %1";

    // A rebuilt thumb is a clone of one the server rendered, so the markup lives
    // in gallery.twig alone: a class or attribute added there is picked up here
    // without a second edit, and the two cannot drift apart.
    const thumbTemplate =
        strip?.querySelector<HTMLElement>("[data-gallery-thumb]")?.closest("li") ?? null;

    function thumbs(): HTMLElement[] {
        return strip ? Array.from(strip.querySelectorAll<HTMLElement>("[data-gallery-thumb]")) : [];
    }

    function applyMain(large: string | null, label: string | null): void {
        if (large) {
            main!.setAttribute("src", large);
        }
        // Keep the prior alt (the product name) when a variant image has no
        // caption, rather than blanking it.
        if (label) {
            main!.setAttribute("alt", label);
        }
    }

    // The transition snapshots the new state one frame after the callback: an
    // image that has not decoded yet is captured as nothing, and the cross-fade
    // lands on the frame's bare background instead of the photo.
    function transition(mutate: () => void, sources: (string | null | undefined)[]): void {
        if (typeof document.startViewTransition !== "function" || prefersReducedMotion()) {
            mutate();
            return;
        }
        void within(decoded(sources), DECODE_BUDGET).then(() => {
            // Without the scope class the whole viewport is captured as `root` and
            // cross-faded with itself, tinting the page for the length of the swap.
            const documentRoot = document.documentElement;
            documentRoot.classList.add(SWAP_SCOPE_CLASS);
            const release = () => documentRoot.classList.remove(SWAP_SCOPE_CLASS);
            document.startViewTransition(mutate).finished.then(release, release);
        });
    }

    function swapMain(large: string | undefined, label: string | undefined): void {
        if (!large || main!.getAttribute("src") === large) {
            return;
        }
        transition(() => applyMain(large, label ?? null), [large]);
    }

    function setActiveThumb(active: HTMLElement | null): void {
        thumbs().forEach((thumb) => thumb.setAttribute("aria-pressed", String(thumb === active)));
    }

    function buildThumb(tile: GalleryTile, index: number): HTMLElement | null {
        if (!thumbTemplate) {
            return null;
        }
        const li = thumbTemplate.cloneNode(true) as HTMLElement;
        const button = li.querySelector<HTMLElement>("[data-gallery-thumb]");
        const image = li.querySelector<HTMLImageElement>("img");
        if (!button || !image) {
            return null;
        }
        button.dataset.large = tile.large;
        button.dataset.label = tile.label ?? "";
        button.setAttribute("aria-pressed", index === 0 ? "true" : "false");
        button.setAttribute("aria-label", labelPattern.replace("%1", String(index + 1)));
        image.src = tile.thumb;
        image.alt = "";
        // The server marks thumbs lazy; a rebuilt strip is already in view.
        image.loading = "eager";
        return li;
    }

    function rebuildStrip(tiles: GalleryTile[]): void {
        if (!strip) {
            return;
        }
        strip.replaceChildren(...tiles.map(buildThumb).filter((node): node is HTMLElement => !!node));
    }

    if (strip) {
        strip.addEventListener("click", (event) => {
            const thumb = (event.target as HTMLElement | null)?.closest<HTMLElement>(
                "[data-gallery-thumb]",
            );
            if (!thumb || !strip.contains(thumb)) {
                return;
            }
            swapMain(thumb.dataset.large, thumb.dataset.label);
            setActiveThumb(thumb);
        });
        // Roving arrow-key navigation across the thumbnail strip.
        strip.addEventListener("keydown", (event) => {
            const thumb = (event.target as HTMLElement | null)?.closest<HTMLElement>(
                "[data-gallery-thumb]",
            );
            if (!thumb) {
                return;
            }
            const step = event.key === "ArrowRight" ? 1 : event.key === "ArrowLeft" ? -1 : 0;
            if (step === 0) {
                return;
            }
            event.preventDefault();
            const list = thumbs();
            const index = list.indexOf(thumb);
            const next = list[(index + step + list.length) % list.length];
            next.focus();
            next.click();
        });
    }

    function onGalleryChange(detail: ProductGalleryChangeEvent): void {
        if (detail.reset) {
            transition(() => {
                if (strip && base.thumbs != null) {
                    strip.innerHTML = base.thumbs;
                }
                applyMain(base.src, base.label);
            }, [base.src]);
            return;
        }

        if (Array.isArray(detail.tiles) && detail.tiles.length) {
            const large = detail.large ?? detail.tiles[0].large;
            const label = detail.label ?? detail.tiles[0].label;
            transition(() => {
                rebuildStrip(detail.tiles as GalleryTile[]);
                applyMain(large, label);
                const list = thumbs();
                if (list.length) {
                    setActiveThumb(list[0]);
                }
            }, [large, ...detail.tiles.map((tile) => tile.thumb)]);
            return;
        }

        // Single-image variant: swap the hero only; the image may not match any
        // thumb, so clear the active state.
        if (detail.large) {
            swapMain(detail.large, detail.label);
            setActiveThumb(null);
        }
    }

    events.observe(CatalogEvent.ProductGalleryChange, onGalleryChange);
    window.addEventListener(LEGACY_VARIANT_IMAGE_EVENT, (event) =>
        onGalleryChange((event as CustomEvent<ProductGalleryChangeEvent>).detail ?? {}),
    );
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
} else {
    init();
}

export { init };
