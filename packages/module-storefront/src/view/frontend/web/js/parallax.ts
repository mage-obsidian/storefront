const PARALLAX = '[data-enable-parallax="1"]';
const DEFAULT_SPEED = 0.3;
const REDUCED_MOTION = "(prefers-reduced-motion: reduce)";

const bound = new WeakSet<Element>();

export function speedOf(element: Element): number {
    const declared = Number.parseFloat(element.getAttribute("data-parallax-speed") ?? "");

    return Number.isFinite(declared) && declared > 0 && declared <= 1 ? declared : DEFAULT_SPEED;
}

export function prefersReducedMotion(view: Window): boolean {
    return view.matchMedia?.(REDUCED_MOTION)?.matches === true;
}

export function shiftFor(top: number, height: number, viewportHeight: number, speed: number): number {
    const travelled = (viewportHeight - top) / (viewportHeight + height);
    const clamped = Math.min(Math.max(travelled, 0), 1);

    return (clamped - 0.5) * height * speed;
}

function layerFor(element: HTMLElement, view: Window): HTMLElement | null {
    const background = view.getComputedStyle(element).backgroundImage;
    if (!background || background === "none") {
        return null;
    }

    const speed = speedOf(element);
    const layer = view.document.createElement("div");
    layer.setAttribute("aria-hidden", "true");
    layer.setAttribute("data-parallax-layer", "");
    Object.assign(layer.style, {
        position: "absolute",
        insetInline: "0",
        top: `${-speed * 50}%`,
        height: `${100 + speed * 100}%`,
        backgroundImage: background,
        backgroundPosition: "center",
        backgroundSize: "cover",
        backgroundRepeat: "no-repeat",
        pointerEvents: "none",
        willChange: "transform",
        zIndex: "0",
    });

    element.style.backgroundImage = "none";
    if (view.getComputedStyle(element).position === "static") {
        element.style.position = "relative";
    }
    element.insertBefore(layer, element.firstChild);

    return layer;
}

function track(element: HTMLElement, layer: HTMLElement, view: Window): void {
    const speed = speedOf(element);
    let pending = 0;

    const paint = (): void => {
        pending = 0;
        const box = element.getBoundingClientRect();
        layer.style.transform =
            `translate3d(0, ${shiftFor(box.top, box.height, view.innerHeight, speed).toFixed(2)}px, 0)`;
    };

    const schedule = (): void => {
        if (pending === 0) {
            pending = view.requestAnimationFrame(paint);
        }
    };

    if (typeof view.IntersectionObserver !== "function") {
        schedule();
        view.addEventListener("scroll", schedule, { passive: true });
        return;
    }

    const observer = new view.IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                schedule();
                view.addEventListener("scroll", schedule, { passive: true });
                return;
            }

            view.removeEventListener("scroll", schedule);
            if (pending !== 0) {
                view.cancelAnimationFrame(pending);
                pending = 0;
            }
        });
    });
    observer.observe(element);
}

export function enhanceParallax(root: ParentNode = document, view: Window = window): void {
    if (prefersReducedMotion(view)) {
        return;
    }

    root.querySelectorAll<HTMLElement>(PARALLAX).forEach((element) => {
        if (bound.has(element)) {
            return;
        }

        const layer = layerFor(element, view);
        if (layer === null) {
            return;
        }

        bound.add(element);
        track(element, layer, view);
    });
}

enhanceParallax();
