/**
 * Custom product options logic, shared by the simple add-to-cart form and the
 * configurable Vue island (one logic module, two hosts). It is framework-free
 * and DOM-driven: it reads the server-rendered option fields (native
 * options[...] names) plus the embedded JSON price config (Magento's
 * getJsonConfig), and exposes the live price delta, accessible required-option
 * validation, a change subscription, and a collector that appends every option
 * field — including file uploads — into a FormData for the AJAX add-to-cart.
 *
 * The fields POST natively without JS; this only enhances pricing/validation.
 */

interface PriceNode {
    prices?: { finalPrice?: { amount?: number | string } };
}

// config[optionId] is either a value-less price node (text/date/file) or a map
// of valueId -> price node (drop-down/radio/checkbox/multiselect).
type OptionConfig = PriceNode & Record<string, PriceNode>;
type OptionsConfig = Record<string, OptionConfig>;

const SELECT_TYPES = new Set(["drop_down", "radio", "checkbox", "multiple"]);

export interface ProductOptions {
    delta: () => number;
    validate: () => boolean;
    onChange: (cb: () => void) => void;
    appendTo: (form: FormData) => void;
}

function amountOf(node: PriceNode | undefined): number {
    const raw = node?.prices?.finalPrice?.amount ?? 0;
    const value = Number(raw);
    return Number.isFinite(value) ? value : 0;
}

function parseConfig(root: HTMLElement): OptionsConfig {
    const holder = root.querySelector("[data-options-config]");
    if (!holder?.textContent) {
        return {};
    }
    try {
        return JSON.parse(holder.textContent) as OptionsConfig;
    } catch {
        return {};
    }
}

export function createProductOptions(root: HTMLElement): ProductOptions {
    const config = parseConfig(root);
    const fieldsets = (): HTMLElement[] => Array.from(root.querySelectorAll<HTMLElement>("[data-option]"));

    /**
     * Selected value ids for a select-style option (drop-down/radio/checkbox/
     * multiple). Reads the `.checked`/`.value`/`.selected` properties rather than
     * the `:checked` pseudo so it behaves the same under jsdom-style runtimes.
     */
    function selectedValueIds(fs: HTMLElement, type: string): string[] {
        if (type === "checkbox") {
            return Array.from(fs.querySelectorAll<HTMLInputElement>("input"))
                .filter((i) => i.checked)
                .map((i) => i.value);
        }
        if (type === "radio") {
            const checked = Array.from(fs.querySelectorAll<HTMLInputElement>("input")).find((i) => i.checked);
            return checked && checked.value ? [checked.value] : [];
        }
        const select = fs.querySelector<HTMLSelectElement>("select");
        if (!select) {
            return [];
        }
        if (type === "multiple") {
            return Array.from(select.options)
                .filter((o) => o.selected)
                .map((o) => o.value)
                .filter((v) => v !== "");
        }
        return select.value !== "" ? [select.value] : [];
    }

    /** Whether a value-less option (text/area/date/file) has a usable value. */
    function isFilled(fs: HTMLElement, type: string): boolean {
        if (SELECT_TYPES.has(type)) {
            return selectedValueIds(fs, type).length > 0;
        }
        if (type === "file") {
            const file = fs.querySelector<HTMLInputElement>('input[type="file"]');
            // A reconfigured line already holds an upload server-side, so the
            // option counts as filled even with an empty input.
            return (!!file?.files && file.files.length > 0) || fs.hasAttribute("data-option-uploaded");
        }
        if (type === "field" || type === "area") {
            const input = fs.querySelector<HTMLInputElement | HTMLTextAreaElement>("input, textarea");
            return !!input && input.value.trim() !== "";
        }
        // Date variants: every rendered part must be chosen.
        const parts = Array.from(fs.querySelectorAll<HTMLSelectElement>("select"));
        return parts.length > 0 && parts.every((p) => p.value !== "");
    }

    function delta(): number {
        let sum = 0;
        for (const fs of fieldsets()) {
            const id = fs.dataset.optionId ?? "";
            const type = fs.dataset.optionType ?? "";
            const cfg = config[id];
            if (!cfg) {
                continue;
            }
            if (SELECT_TYPES.has(type)) {
                for (const valueId of selectedValueIds(fs, type)) {
                    sum += amountOf(cfg[valueId]);
                }
            } else if (isFilled(fs, type)) {
                sum += amountOf(cfg);
            }
        }
        return sum;
    }

    function setError(fs: HTMLElement, message: string): void {
        const node = fs.querySelector<HTMLElement>("[data-option-error]");
        if (!node) {
            return;
        }
        node.textContent = message;
        node.hidden = message === "";
    }

    function validate(): boolean {
        let firstInvalid: HTMLElement | null = null;
        for (const fs of fieldsets()) {
            if (!fs.hasAttribute("data-required")) {
                continue;
            }
            const type = fs.dataset.optionType ?? "";
            if (isFilled(fs, type)) {
                setError(fs, "");
            } else {
                setError(fs, "This is a required field.");
                firstInvalid ??= fs;
            }
        }
        firstInvalid?.querySelector<HTMLElement>("input, select, textarea")?.focus();
        return firstInvalid === null;
    }

    /**
     * Keep Magento's `options_<id>_file_action` in step with the input: `save_old`
     * restores the file already attached to the line being reconfigured, so it has
     * to become `save_new` the moment a replacement is picked — otherwise the
     * upload is accepted by the browser and then discarded by the server.
     */
    function syncFileAction(target: EventTarget | null): void {
        if (!(target instanceof HTMLInputElement) || target.type !== "file") {
            return;
        }
        const fs = target.closest<HTMLElement>("[data-option]");
        const action = fs?.querySelector<HTMLInputElement>('input[name$="_file_action"]');
        if (!action) {
            return;
        }
        const chose = !!target.files && target.files.length > 0;
        // Clearing the input only falls back to `save_old` where an old file
        // exists; without one there is nothing to keep.
        action.value = chose || !fs?.hasAttribute("data-option-uploaded") ? "save_new" : "save_old";
    }

    function onChange(cb: () => void): void {
        root.addEventListener("change", cb);
        root.addEventListener("input", cb);
    }

    root.addEventListener("change", (event) => syncFileAction(event.target));

    function appendTo(form: FormData): void {
        const inputs = root.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>(
            "input[name], select[name], textarea[name]",
        );
        for (const input of inputs) {
            if (input instanceof HTMLInputElement && input.type === "file") {
                if (input.files && input.files.length > 0) {
                    form.append(input.name, input.files[0]);
                }
                continue;
            }
            if (input instanceof HTMLInputElement && (input.type === "radio" || input.type === "checkbox")) {
                if (input.checked && input.value !== "") {
                    form.append(input.name, input.value);
                }
                continue;
            }
            if (input instanceof HTMLSelectElement && input.multiple) {
                for (const opt of Array.from(input.options)) {
                    if (opt.selected && opt.value !== "") {
                        form.append(input.name, opt.value);
                    }
                }
                continue;
            }
            if (input.value !== "") {
                form.append(input.name, input.value);
            }
        }
    }

    return { delta, validate, onChange, appendTo };
}
