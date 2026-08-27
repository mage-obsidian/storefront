import { beforeEach, describe, expect, it, vi } from "vitest";
import {
    bindReCaptchaFields,
    loadReCaptchaApi,
    mountReCaptcha,
    resetReCaptchaModule,
    tokenFor,
    type GReCaptcha,
} from "./recaptcha.ts";

interface Rendered {
    element: HTMLElement;
    parameters: Record<string, unknown>;
}

const settings = (over: Record<string, unknown> = {}): string =>
    JSON.stringify({
        formKey: "customer_login",
        sitekey: "site-key",
        size: "normal",
        theme: "light",
        badge: "",
        lang: "en",
        invisible: false,
        ...over,
    });

const render = (attribute = settings()): HTMLElement => {
    document.body.innerHTML = `
        <form action="/login" method="post">
            <div class="field-recaptcha" data-recaptcha='${attribute}'></div>
        </form>`;
    return document.querySelector(".field-recaptcha") as HTMLElement;
};

const stubApi = (): { api: GReCaptcha; rendered: Rendered[]; responses: Map<number, string> } => {
    const rendered: Rendered[] = [];
    const responses = new Map<number, string>();

    const api: GReCaptcha = {
        render(element, parameters) {
            rendered.push({ element, parameters: parameters as unknown as Record<string, unknown> });
            return rendered.length - 1;
        },
        execute(widgetId) {
            const callback = rendered[widgetId]?.parameters.callback as ((token: string) => void) | undefined;
            callback?.("token-from-execute");
        },
        reset(widgetId) {
            responses.delete(widgetId);
        },
        getResponse(widgetId) {
            return responses.get(widgetId) ?? "";
        },
    };

    (globalThis as unknown as { grecaptcha?: GReCaptcha }).grecaptcha = api;
    return { api, rendered, responses };
};

beforeEach(() => {
    document.body.innerHTML = "";
    document.head.querySelectorAll("script").forEach((script) => script.remove());
    resetReCaptchaModule();
    delete (globalThis as unknown as { grecaptcha?: GReCaptcha }).grecaptcha;
});

describe("loadReCaptchaApi", () => {
    // The vendor's API is what renders the widget; nothing here can be verified
    // without it, so the script has to be injected rather than assumed present.
    it("injects the vendor script once and resolves when it announces itself", async () => {
        const pending = loadReCaptchaApi("es");
        const script = document.head.querySelector("script") as HTMLScriptElement;

        expect(script.src).toContain("render=explicit");
        expect(script.src).toContain("hl=es");

        stubApi();
        (globalThis as unknown as Record<string, () => void>).mageObsidianReCaptchaReady();

        await expect(pending).resolves.toBeDefined();
        void loadReCaptchaApi("es");
        expect(document.head.querySelectorAll("script")).toHaveLength(1);
    });

    it("resolves straight away when the API is already on the page", async () => {
        const { api } = stubApi();

        await expect(loadReCaptchaApi()).resolves.toBe(api);
        expect(document.head.querySelector("script")).toBeNull();
    });
});

describe("mountReCaptcha", () => {
    it("renders the widget with what the store configured", async () => {
        const { rendered } = stubApi();
        const element = render(settings({ theme: "dark", size: "compact" }));

        await mountReCaptcha(element);

        expect(rendered[0].element).toBe(element);
        expect(rendered[0].parameters.sitekey).toBe("site-key");
        expect(rendered[0].parameters.theme).toBe("dark");
        expect(rendered[0].parameters.size).toBe("compact");
    });

    it("renders an invisible challenge as invisible whatever size says", async () => {
        const { rendered } = stubApi();

        await mountReCaptcha(render(settings({ invisible: true, size: "normal", badge: "bottomright" })));

        expect(rendered[0].parameters.size).toBe("invisible");
        expect(rendered[0].parameters.badge).toBe("bottomright");
    });

    it("renders one widget per form however often it is asked", async () => {
        const { rendered } = stubApi();
        const element = render();

        await mountReCaptcha(element);
        await mountReCaptcha(element);

        expect(rendered).toHaveLength(1);
    });

    // A slot the platform left empty carries no settings at all.
    it("does nothing for an element with no settings", async () => {
        const { rendered } = stubApi();
        document.body.innerHTML = '<div class="field-recaptcha"></div>';

        await mountReCaptcha(document.querySelector(".field-recaptcha") as HTMLElement);

        expect(rendered).toHaveLength(0);
    });

    it("does nothing when the settings are not readable", async () => {
        const { rendered } = stubApi();
        document.body.innerHTML = `<div class="field-recaptcha" data-recaptcha="{nonsense"></div>`;

        await mountReCaptcha(document.querySelector(".field-recaptcha") as HTMLElement);

        expect(rendered).toHaveLength(0);
    });
});

describe("tokenFor", () => {
    it("hands back the token the shopper already produced", async () => {
        const { responses } = stubApi();
        await mountReCaptcha(render());
        responses.set(0, "ticked-token");

        await expect(tokenFor("customer_login")).resolves.toBe("ticked-token");
    });

    // Nothing for the shopper to click, so the token has to be asked for.
    it("executes an invisible challenge to get one", async () => {
        stubApi();
        await mountReCaptcha(render(settings({ invisible: true })));

        await expect(tokenFor("customer_login")).resolves.toBe("token-from-execute");
    });

    // A form the store did not put a challenge on must not be made to wait for
    // one that will never arrive.
    it("answers null for a form that carries no challenge", async () => {
        stubApi();

        await expect(tokenFor("place_order")).resolves.toBeNull();
    });
});

describe("bindReCaptchaFields", () => {
    it("mounts every slot on the page", async () => {
        const { rendered } = stubApi();
        render();

        bindReCaptchaFields(document.body);
        await Promise.resolve();
        await Promise.resolve();

        expect(rendered).toHaveLength(1);
    });

    it("holds an invisible form's submit until the token exists", async () => {
        stubApi();
        const element = render(settings({ invisible: true }));
        const form = element.closest("form") as HTMLFormElement;
        const submitted = vi.fn();
        form.requestSubmit = submitted;

        bindReCaptchaFields(document.body);
        await Promise.resolve();
        await Promise.resolve();

        const event = new Event("submit", { bubbles: true, cancelable: true });
        form.dispatchEvent(event);
        expect(event.defaultPrevented).toBe(true);

        await Promise.resolve();
        await Promise.resolve();
        expect(submitted).toHaveBeenCalled();
    });

    // A visible checkbox has already written its token into the form, so holding
    // the submit would only delay it.
    it("lets a visible challenge submit on its own", async () => {
        stubApi();
        const element = render();
        const form = element.closest("form") as HTMLFormElement;

        bindReCaptchaFields(document.body);
        await Promise.resolve();

        const event = new Event("submit", { bubbles: true, cancelable: true });
        form.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(false);
    });
});
