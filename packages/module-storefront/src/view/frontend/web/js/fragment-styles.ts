export interface FragmentStyleAdopter {
    adopt(root: ParentNode, key: string): number;
}

type SheetHost = Document & { adoptedStyleSheets: CSSStyleSheet[] };

const STYLE_SELECTOR = 'style';

function canAdopt(doc: Document): doc is SheetHost {
    const adopted = (doc as SheetHost).adoptedStyleSheets as CSSStyleSheet[] | undefined;

    return (
        typeof adopted?.length === 'number' &&
        typeof adopted[Symbol.iterator] === 'function' &&
        typeof CSSStyleSheet === 'function' &&
        typeof CSSStyleSheet.prototype?.replaceSync === 'function'
    );
}

export function createFragmentStyleAdopter(doc: Document): FragmentStyleAdopter {
    const sheets = new Map<string, CSSStyleSheet>();

    const sheetFor = (host: SheetHost, key: string): CSSStyleSheet => {
        const known = sheets.get(key);
        if (known) {
            return known;
        }

        const sheet = new CSSStyleSheet();
        sheets.set(key, sheet);
        host.adoptedStyleSheets = [...host.adoptedStyleSheets, sheet];

        return sheet;
    };

    return {
        adopt(root: ParentNode, key: string): number {
            if (!canAdopt(doc)) {
                return 0;
            }

            const blocks = Array.from(root.querySelectorAll<HTMLStyleElement>(STYLE_SELECTOR));
            if (blocks.length === 0 && !sheets.has(key)) {
                return 0;
            }

            const css = blocks.map((block) => block.textContent ?? '').join('\n');

            try {
                sheetFor(doc, key).replaceSync(css);
            } catch {
                return 0;
            }

            blocks.forEach((block) => block.remove());

            return blocks.length;
        },
    };
}

export function declaredValues(css: string, property: string): Set<string> {
    const found = new Set<string>();
    if (typeof CSSStyleSheet !== 'function' || typeof CSSStyleSheet.prototype?.replaceSync !== 'function') {
        return found;
    }

    const sheet = new CSSStyleSheet();
    try {
        sheet.replaceSync(css);
    } catch {
        return found;
    }

    Array.from(sheet.cssRules).forEach((rule) => {
        const value = (rule as CSSStyleRule).style?.getPropertyValue(property) ?? '';
        if (value !== '' && value !== 'none') {
            found.add(value);
        }
    });

    return found;
}
