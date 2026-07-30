const SELECTOR = "select[data-nav-select]";
const BOUND = "navSelectBound";

export function bindNavSelect(select: HTMLSelectElement, navigate: (url: string) => void): void {
    if (select.dataset[BOUND]) {
        return;
    }
    select.dataset[BOUND] = "1";
    select.addEventListener("change", () => {
        if (select.value) {
            navigate(select.value);
        }
    });
}

export function bindNavSelects(
    root: ParentNode = document,
    navigate: (url: string) => void = (url) => window.location.assign(url),
): void {
    root.querySelectorAll<HTMLSelectElement>(SELECTOR).forEach((select) =>
        bindNavSelect(select, navigate),
    );
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => bindNavSelects());
} else {
    bindNavSelects();
}
