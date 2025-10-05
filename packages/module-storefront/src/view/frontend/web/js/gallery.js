/**
 * Product gallery enhancer. The gallery is server-rendered (LCP-friendly,
 * crawlable); this only adds interactivity: clicking a thumb swaps the main
 * image, and the same main slot listens for `obsidian:variant-image` so the
 * configurable island can swap it when a variant is chosen. Image swaps use the
 * View Transitions API for a crossfade, disabled under prefers-reduced-motion.
 * Pure DOM, one listener set per page — no framework cost.
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
    const thumbs = Array.from(root.querySelectorAll('[data-gallery-thumb]'));

    // Stable name so the crossfade only animates this element.
    main.style.viewTransitionName = 'pdp-hero';

    function swapMain(large, label) {
        if (!large || main.getAttribute('src') === large) {
            return;
        }
        const apply = () => {
            main.setAttribute('src', large);
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
        thumbs.forEach((thumb) => thumb.setAttribute('aria-pressed', String(thumb === active)));
    }

    thumbs.forEach((thumb, index) => {
        thumb.addEventListener('click', () => {
            swapMain(thumb.dataset.large, thumb.dataset.label);
            setActiveThumb(thumb);
        });
        // Roving arrow-key navigation across the thumbnail strip.
        thumb.addEventListener('keydown', (event) => {
            const step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
            if (step === 0) {
                return;
            }
            event.preventDefault();
            const next = thumbs[(index + step + thumbs.length) % thumbs.length];
            next.focus();
            next.click();
        });
    });

    window.addEventListener(VARIANT_EVENT, (event) => {
        const { large, label } = event.detail ?? {};
        if (!large) {
            return;
        }
        swapMain(large, label);
        // A variant image may not match any thumb; clear the active state.
        setActiveThumb(null);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
    init();
}

export { init };
