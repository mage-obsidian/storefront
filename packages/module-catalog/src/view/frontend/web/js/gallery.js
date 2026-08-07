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
import events from 'MageObsidian_ModernFrontend::js/events';
import { CatalogEvent, LEGACY_VARIANT_IMAGE_EVENT } from 'MageObsidian_Catalog::js/catalog-events';

const SWAP_SCOPE_CLASS = 'pdp-gallery-swap';
const DECODE_BUDGET = 400;

const prefersReducedMotion = () =>
    typeof window.matchMedia === 'function'
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const decoded = (sources) =>
    Promise.all(sources.filter(Boolean).map((src) => {
        const image = new Image();
        image.src = src;
        return typeof image.decode === 'function' ? image.decode().catch(() => {}) : Promise.resolve();
    }));

const within = (promise, ms) =>
    Promise.race([promise, new Promise((resolve) => { setTimeout(resolve, ms); })]);

function init() {
    const root = document.querySelector('[data-pdp]');
    if (!root) {
        return;
    }
    const main = root.querySelector('[data-gallery-main]');
    if (!main) {
        return;
    }
    const strip = root.querySelector('[data-gallery-thumbs]');

    // Snapshot the base product's gallery so a variant reset can restore it.
    const base = {
        thumbs: strip ? strip.innerHTML : null,
        src: main.getAttribute('src'),
        label: main.getAttribute('alt'),
    };
    const labelPattern = strip?.dataset.thumbLabel ?? 'Show image %1';

    function thumbs() {
        return strip ? Array.from(strip.querySelectorAll('[data-gallery-thumb]')) : [];
    }

    function applyMain(large, label) {
        main.setAttribute('src', large);
        // Keep the prior alt (the product name) when a variant image has no
        // caption, rather than blanking it.
        if (label) {
            main.setAttribute('alt', label);
        }
    }

    // The transition snapshots the new state one frame after the callback: an
    // image that has not decoded yet is captured as nothing, and the cross-fade
    // lands on the frame's bare background instead of the photo.
    function transition(mutate, sources) {
        if (typeof document.startViewTransition !== 'function' || prefersReducedMotion()) {
            mutate();
            return;
        }
        void within(decoded(sources), DECODE_BUDGET).then(() => {
            // Without the scope class the whole viewport is captured as `root` and
            // cross-faded with itself, tinting the page for the length of the swap.
            const root = document.documentElement;
            root.classList.add(SWAP_SCOPE_CLASS);
            const release = () => root.classList.remove(SWAP_SCOPE_CLASS);
            document.startViewTransition(mutate).finished.then(release, release);
        });
    }

    function swapMain(large, label) {
        if (!large || main.getAttribute('src') === large) {
            return;
        }
        transition(() => applyMain(large, label), [large]);
    }

    function setActiveThumb(active) {
        thumbs().forEach((thumb) => thumb.setAttribute('aria-pressed', String(thumb === active)));
    }

    // Build a thumb via the DOM API (not innerHTML) so URLs/labels never need
    // manual escaping — the property setters handle it.
    function buildThumb(tile, index) {
        const li = document.createElement('li');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'pdp__thumb block w-full overflow-hidden rounded-edge border border-transparent bg-alabaster-raised transition-colors aria-pressed:border-ink';
        button.setAttribute('data-gallery-thumb', '');
        button.dataset.large = tile.large;
        button.dataset.label = tile.label ?? '';
        button.setAttribute('aria-pressed', index === 0 ? 'true' : 'false');
        button.setAttribute('aria-label', labelPattern.replace('%1', String(index + 1)));
        const img = document.createElement('img');
        img.className = 'aspect-[4/5] h-full w-full object-cover';
        img.src = tile.thumb;
        img.alt = '';
        img.loading = 'eager';
        img.decoding = 'async';
        button.appendChild(img);
        li.appendChild(button);
        return li;
    }

    function rebuildStrip(tiles) {
        if (!strip) {
            return;
        }
        strip.replaceChildren(...tiles.map(buildThumb));
    }

    if (strip) {
        strip.addEventListener('click', (event) => {
            const thumb = event.target.closest('[data-gallery-thumb]');
            if (!thumb || !strip.contains(thumb)) {
                return;
            }
            swapMain(thumb.dataset.large, thumb.dataset.label);
            setActiveThumb(thumb);
        });
        // Roving arrow-key navigation across the thumbnail strip.
        strip.addEventListener('keydown', (event) => {
            const thumb = event.target.closest('[data-gallery-thumb]');
            if (!thumb) {
                return;
            }
            const step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
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

    function onGalleryChange(detail) {
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
                rebuildStrip(detail.tiles);
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
    window.addEventListener(LEGACY_VARIANT_IMAGE_EVENT, (event) => onGalleryChange(event.detail ?? {}));
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
    init();
}

export { init };
