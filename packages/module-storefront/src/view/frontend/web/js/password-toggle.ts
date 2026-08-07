const WRAPPER_CLASS = "field-password";
const BUTTON_CLASS = "field-password__toggle";
const ICON_BASE = "__MAGE_OBSIDIAN_ICONS__";

interface ToggleLabels {
    show: string;
    hide: string;
}

function labelsFor(input: HTMLInputElement): ToggleLabels {
    const form = input.form;
    return {
        show: form?.dataset.pwdShow || "Show password",
        hide: form?.dataset.pwdHide || "Hide password",
    };
}

function icon(name: string, doc: Document): SVGElement {
    const base = (window as Window & { [ICON_BASE]?: { baseUrl?: string } })[ICON_BASE]?.baseUrl ?? "";
    const svg = doc.createElementNS("http://www.w3.org/2000/svg", "svg");
    svg.setAttribute("viewBox", "0 0 20 20");
    svg.setAttribute("fill", "currentColor");
    svg.setAttribute("aria-hidden", "true");
    svg.setAttribute("width", "20");
    svg.setAttribute("height", "20");
    const use = doc.createElementNS("http://www.w3.org/2000/svg", "use");
    use.setAttribute("href", `${base}20/solid/${name}.svg#icon`);
    svg.append(use);
    return svg;
}

function apply(input: HTMLInputElement, doc: Document): void {
    if (input.parentElement?.classList.contains(WRAPPER_CLASS)) {
        return;
    }

    const labels = labelsFor(input);
    const wrapper = doc.createElement("span");
    wrapper.className = WRAPPER_CLASS;
    input.replaceWith(wrapper);
    wrapper.append(input);

    const button = doc.createElement("button");
    button.type = "button";
    button.className = BUTTON_CLASS;
    button.setAttribute("aria-pressed", "false");
    button.setAttribute("aria-label", labels.show);
    button.setAttribute("aria-controls", input.id);
    button.append(icon("eye", doc));
    wrapper.append(button);

    button.addEventListener("click", () => {
        const revealed = input.type === "text";
        input.type = revealed ? "password" : "text";
        button.setAttribute("aria-pressed", String(!revealed));
        button.setAttribute("aria-label", revealed ? labels.show : labels.hide);
        button.replaceChildren(icon(revealed ? "eye" : "eye-slash", doc));
        input.focus();
    });
}

export function bindPasswordToggles(root: ParentNode = document, doc: Document = document): void {
    root.querySelectorAll<HTMLInputElement>('.field input[type="password"]').forEach((input) => apply(input, doc));
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => bindPasswordToggles(), { once: true });
} else {
    bindPasswordToggles();
}
