const OVERFLOW = "overflow";
const PADDING_RIGHT = "padding-right";
const SCROLLBAR_GUTTER = "scrollbar-gutter";

interface LockState {
    depth: number;
    overflow: string;
    paddingRight: string;
    scrollbarGutter: string;
}

const locks = new WeakMap<Document, LockState>();

const gutterWidth = (doc: Document): number => {
    const view = doc.defaultView;

    return view ? Math.max(0, view.innerWidth - doc.documentElement.clientWidth) : 0;
};

/**
 * Hides the page scrollbar without moving the page, and — the part that is not
 * obvious — releases `scrollbar-gutter`. While the gutter is reserved, the
 * initial containing block stays narrower than the window, so `inset: 0`, `100vw`
 * and `100dvw` all resolve short of the right edge and an overlay leaves a bare
 * strip of canvas behind. Padding the body by the same width keeps the content
 * where it was.
 */
export function lockScroll(doc: Document = document): void {
    const held = locks.get(doc);
    if (held) {
        held.depth += 1;

        return;
    }

    const root = doc.documentElement;
    const body = doc.body;
    const gutter = gutterWidth(doc);

    locks.set(doc, {
        depth: 1,
        overflow: body.style.getPropertyValue(OVERFLOW),
        paddingRight: body.style.getPropertyValue(PADDING_RIGHT),
        scrollbarGutter: root.style.getPropertyValue(SCROLLBAR_GUTTER),
    });

    body.style.setProperty(OVERFLOW, "hidden");
    if (gutter > 0) {
        const view = doc.defaultView;
        const base = view ? parseFloat(view.getComputedStyle(body).paddingRight) || 0 : 0;
        body.style.setProperty(PADDING_RIGHT, `${base + gutter}px`);
        root.style.setProperty(SCROLLBAR_GUTTER, "auto");
    }
}

export function unlockScroll(doc: Document = document): void {
    const held = locks.get(doc);
    if (!held) {
        return;
    }
    held.depth -= 1;
    if (held.depth > 0) {
        return;
    }
    locks.delete(doc);

    const restore = (style: CSSStyleDeclaration, property: string, value: string): void => {
        if (value) {
            style.setProperty(property, value);
        } else {
            style.removeProperty(property);
        }
    };

    restore(doc.body.style, OVERFLOW, held.overflow);
    restore(doc.body.style, PADDING_RIGHT, held.paddingRight);
    restore(doc.documentElement.style, SCROLLBAR_GUTTER, held.scrollbarGutter);
}
