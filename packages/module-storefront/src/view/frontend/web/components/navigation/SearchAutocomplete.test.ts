import { describe, it, expect, afterEach, vi } from "vitest";
import { mount } from "@vue/test-utils";
import SearchAutocomplete from "./SearchAutocomplete.vue";

// Header quick-search. The icon button toggles a panel with a real GET <form>
// (works without JS) plus an accessible combobox over Magento's native suggest
// endpoint ({title, num_results}). Keyboard: ArrowDown/Up move the active option,
// Enter on an active option runs that search, otherwise the form submits.
const props = {
    actionUrl: "https://shop.test/catalogsearch/result/",
    queryParam: "q",
    suggestUrl: "https://shop.test/search/ajax/suggest/",
    minLength: 3,
    maxLength: 128,
    suggestionsEnabled: true,
};

const mountSearch = () => mount(SearchAutocomplete, { props, attachTo: document.body });

const suggestResponse = (items: Array<{ title: string; num_results?: number }>) => {
    global.fetch = vi.fn().mockResolvedValue({ ok: true, json: async () => items }) as unknown as typeof fetch;
};

afterEach(() => {
    document.body.innerHTML = "";
    vi.restoreAllMocks();
});

describe("SearchAutocomplete", () => {
    it("renders a collapsed trigger with no input until opened", () => {
        const wrapper = mountSearch();

        const trigger = wrapper.get("button");
        expect(trigger.attributes("aria-expanded")).toBe("false");
        expect(wrapper.find("input").exists()).toBe(false);

        wrapper.unmount();
    });

    it("opens a GET form combobox bound to the native result URL", async () => {
        const wrapper = mountSearch();

        await wrapper.get("button").trigger("click");

        const form = wrapper.get("form");
        expect(form.attributes("method")).toBe("get");
        expect(form.attributes("action")).toBe(props.actionUrl);

        const input = wrapper.get("input");
        expect(input.attributes("role")).toBe("combobox");
        expect(input.attributes("name")).toBe("q");
        expect(input.attributes("aria-autocomplete")).toBe("list");
        expect(wrapper.get("button[type='submit']").exists()).toBe(true);

        wrapper.unmount();
    });

    it("fetches and lists native term suggestions once opened", async () => {
        suggestResponse([
            { title: "jacket", num_results: 12 },
            { title: "jeans", num_results: 8 },
        ]);
        const wrapper = mountSearch();
        await wrapper.get("button").trigger("click");

        await wrapper.vm.fetchSuggestions("jac");
        await wrapper.vm.$nextTick();

        const options = wrapper.findAll("[role='option']");
        expect(options).toHaveLength(2);
        expect(options[0].text()).toContain("jacket");
        expect(options[0].text()).toContain("12");
        expect(wrapper.get("[role='listbox']").exists()).toBe(true);

        wrapper.unmount();
    });

    it("does not suggest below the minimum query length", async () => {
        suggestResponse([{ title: "jacket" }]);
        const wrapper = mountSearch();
        await wrapper.get("button").trigger("click");

        await wrapper.vm.fetchSuggestions("ja");

        expect(global.fetch).not.toHaveBeenCalled();
        expect(wrapper.findAll("[role='option']")).toHaveLength(0);

        wrapper.unmount();
    });

    it("moves the active option with the down arrow and reflects it in ARIA", async () => {
        suggestResponse([{ title: "jacket" }, { title: "jeans" }]);
        const wrapper = mountSearch();
        await wrapper.get("button").trigger("click");
        await wrapper.vm.fetchSuggestions("jac");
        await wrapper.vm.$nextTick();

        const input = wrapper.get("input");
        await input.trigger("keydown", { key: "ArrowDown" });

        const active = wrapper.get("[aria-selected='true']");
        expect(active.text()).toContain("jacket");
        expect(input.attributes("aria-activedescendant")).toBe(active.attributes("id"));

        wrapper.unmount();
    });

    it("skips the suggest fetch entirely when suggestions are disabled", async () => {
        suggestResponse([{ title: "jacket" }]);
        const wrapper = mount(SearchAutocomplete, {
            props: { ...props, suggestionsEnabled: false },
            attachTo: document.body,
        });
        await wrapper.get("button").trigger("click");

        await wrapper.vm.fetchSuggestions("jacket");

        expect(global.fetch).not.toHaveBeenCalled();

        wrapper.unmount();
    });
});
