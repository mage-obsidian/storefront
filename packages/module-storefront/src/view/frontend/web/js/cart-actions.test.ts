import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";

async function loadCartActions() {
    vi.resetModules();
    const eventsModule = await import("MageObsidian_ModernFrontend::js/events");
    const notifications = await import("./notifications.ts");
    const sessionMessages = await import("./session-messages.ts");
    const customerData = await import("MageObsidian_ModernFrontend::js/customer-data");

    customerData.__reset();
    customerData.__setSection("cart", { summary_count: 0 });
    sessionMessages.observeSectionMessages();

    const toast = vi.fn();
    eventsModule.default.observe(notifications.NOTIFICATION_EVENT, toast);

    await import("./cart-actions.ts");

    return { events: eventsModule.default, toast };
}

function addToCartForm(): HTMLFormElement {
    document.body.innerHTML = `
        <form data-add-to-cart action="/checkout/cart/add" method="post">
            <input type="hidden" name="product" value="7">
            <button type="submit">Add to cart</button>
        </form>`;
    return document.querySelector("form") as HTMLFormElement;
}

function submit(form: HTMLFormElement): void {
    form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
}

beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: true }));
    document.cookie = "form_key=ck; path=/";
});

afterEach(() => {
    vi.unstubAllGlobals();
    document.body.innerHTML = "";
});

describe("cart-actions add-to-cart enhancer", () => {
    it("announces the add when nothing else claimed it", async () => {
        const { toast } = await loadCartActions();
        const form = addToCartForm();

        submit(form);
        await vi.waitFor(() => expect(toast).toHaveBeenCalled());

        expect(toast.mock.calls.at(-1)?.[0].message).toBe("Added to cart");
        expect(toast.mock.calls.at(-1)?.[0].tone).toBe("success");
    });

    it("stays quiet when an after observer claimed the announcement", async () => {
        const { events, toast } = await loadCartActions();
        const claimed = vi.fn((data) => {
            data.result.announced = true;
        });
        events.observe("cart_add_after", claimed);
        const form = addToCartForm();
        const button = form.querySelector("button") as HTMLButtonElement;

        submit(form);
        await vi.waitFor(() => expect(button.hasAttribute("aria-busy")).toBe(false));

        expect(claimed).toHaveBeenCalled();
        expect(toast).not.toHaveBeenCalled();
    });

    it("keeps the POST out of the browser's hands", async () => {
        await loadCartActions();
        const form = addToCartForm();
        const event = new Event("submit", { bubbles: true, cancelable: true });

        form.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
    });
});
