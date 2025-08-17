/**
 * Product gallery enhancer. The gallery is server-rendered (LCP-friendly,
 * crawlable); this only adds interactivity: clicking a thumb swaps the main
 * image, and the strip listens for `obsidian:variant-image` so the configurable
 * island can drive it when a variant is chosen — swapping the hero, rebuilding
 * the whole thumbnail strip from the variant's media, or resetting back to the
 * base product. Image swaps use the View Transitions API for a crossfade,
 * disabled under prefers-reduced-motion. Listeners are delegated on the strip
 * container so rebuilt thumbs stay interactive without re-binding.
 */
const VARIANT_EVENT = 'obsidian:variant-image';

const prefersReducedMotion = () =>
    typeof window.matchMedia === 'function'
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

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

    // Stable name so the crossfade only animates this element.
    main.style.viewTransitionName = 'pdp-hero';

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

    function swapMain(large, label) {
        if (!large || main.getAttribute('src') === large) {
            return;
        }
        const apply = () => {
            main.setAttribute('src', large);
            // Keep the prior alt (the product name) when a variant image has no
            // caption, rather than blanking it.
            if (label) {
                main.setAttribute('alt', label);
            }
        };
        if (typeof document.startViewTransition === 'function' && !prefersReducedMotion()) {
            document.startViewTransition(apply);
        } else {
            apply();
        }
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
        img.loading = 'lazy';
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

    window.addEventListener(VARIANT_EVENT, (event) => {
        const detail = event.detail ?? {};

        if (detail.reset) {
            if (strip && base.thumbs != null) {
                strip.innerHTML = base.thumbs;
            }
            swapMain(base.src, base.label);
            return;
        }

        if (Array.isArray(detail.tiles) && detail.tiles.length) {
            rebuildStrip(detail.tiles);
            const list = thumbs();
            swapMain(detail.large ?? detail.tiles[0].large, detail.label ?? detail.tiles[0].label);
            if (list.length) {
                setActiveThumb(list[0]);
            }
            return;
        }

        // Single-image variant: swap the hero only; the image may not match any
        // thumb, so clear the active state.
        if (detail.large) {
            swapMain(detail.large, detail.label);
            setActiveThumb(null);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
    init();
}

export { init };
