import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import CancelOrder from "./CancelOrder.vue";

const REASONS = { reason1: "No longer needed", reason2: "Placed by mistake" };

function mountCancel() {
    return mount(CancelOrder, {
        props: {
            orderId: 42,
            realOrderId: "000000042",
            reasons: REASONS,
            graphqlUrl: "https://shop.test/graphql",
            labels: { cancel: "Cancel Order", confirm: "Confirm", close: "Close" },
        },
        attachTo: document.body,
    });
}

describe("CancelOrder", () => {
    beforeEach(() => {
        vi.restoreAllMocks();
    });

    it("normalizes the keyed reasons map into a selectable list", () => {
        const wrapper = mountCancel();
        const options = wrapper.findAll("option");

        expect(options).toHaveLength(2);
        expect(options[0].element.value).toBe("No longer needed");
        expect(wrapper.find("select").element.value).toBe("No longer needed");
    });

    it("posts the cancelOrder mutation with a base64 order id and the chosen reason", async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            json: async () => ({ data: { cancelOrder: { error: null, order: { status: "Canceled" } } } }),
        });
        vi.stubGlobal("fetch", fetchMock);
        // jsdom/happy-dom: stub reload so the success path does not throw.
        const reload = vi.fn();
        Object.defineProperty(window, "location", { value: { reload }, writable: true });

        const wrapper = mountCancel();
        await wrapper.find("button").trigger("click");
        const confirm = wrapper.findAll("dialog button").find((b) => b.text() === "Confirm");
        await confirm?.trigger("click");
        await Promise.resolve();

        expect(fetchMock).toHaveBeenCalledOnce();
        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe("https://shop.test/graphql");
        const body = JSON.parse(init.body);
        expect(body.query).toContain("cancelOrder");
        expect(body.variables.order_id).toBe(btoa("42"));
        expect(body.variables.reason).toBe("No longer needed");
        expect(init.headers["X-Requested-With"]).toBe("XMLHttpRequest");
    });

    it("surfaces a returned error instead of reloading", async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            json: async () => ({ data: { cancelOrder: { error: "Order cannot be canceled" } } }),
        });
        vi.stubGlobal("fetch", fetchMock);

        const wrapper = mountCancel();
        await wrapper.find("button").trigger("click");
        const confirm = wrapper.findAll("dialog button").find((b) => b.text() === "Confirm");
        await confirm?.trigger("click");
        await Promise.resolve();
        await Promise.resolve();

        expect(wrapper.find(".form-banner").text()).toBe("Order cannot be canceled");
    });
});
