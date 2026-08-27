import { beforeEach, describe, expect, it, vi } from "vitest";
import { bindCaptchaReload, refreshCaptcha } from "./captcha-refresh.ts";

// The module binds itself to `document` on import, capturing the real fetch. The
// container stays detached so a click never reaches that listener and the suite
// never leaves the process.
let container: HTMLElement;

const render = (): HTMLElement => {
    container = document.createElement("div");
    container.innerHTML = `
        <form action="/login" method="post">
            <div class="field-captcha" data-captcha="user_login" data-captcha-refresh="/captcha/refresh/">
                <input name="captcha[user_login]">
                <img data-captcha-image src="/media/captcha/old.png">
                <button type="button" data-captcha-reload><span>Reload captcha</span></button>
            </div>
        </form>`;
    return container.querySelector(".field-captcha") as HTMLElement;
};

const answering = (payload: unknown, ok = true): typeof fetch =>
    vi.fn().mockResolvedValue({ ok, json: async () => payload }) as unknown as typeof fetch;

beforeEach(() => {
    document.body.innerHTML = "";
});

describe("refreshCaptcha", () => {
    it("asks the platform for a new image and swaps it in", async () => {
        const field = render();
        const fetcher = answering({ imgSrc: "/media/captcha/new.png" });

        await refreshCaptcha(field, fetcher);

        const [url, init] = (fetcher as unknown as ReturnType<typeof vi.fn>).mock.calls[0];
        expect(url).toBe("/captcha/refresh/");
        expect(init.method).toBe("POST");
        // Without this the platform answers 302 and the image never changes.
        expect(init.headers["X-Requested-With"]).toBe("XMLHttpRequest");
        expect(JSON.parse(init.body)).toEqual({ formId: "user_login" });
        expect(field.querySelector("img")?.getAttribute("src")).toBe("/media/captcha/new.png");
    });

    // The controller is a POST action that reads the form id from the body; a
    // session that has moved on answers without one rather than erroring.
    it("keeps the image it has when the answer carries no replacement", async () => {
        const field = render();

        await refreshCaptcha(field, answering({}));

        expect(field.querySelector("img")?.getAttribute("src")).toBe("/media/captcha/old.png");
    });

    it("keeps the image it has when the request fails", async () => {
        const field = render();

        await refreshCaptcha(field, answering({ imgSrc: "/media/captcha/new.png" }, false));

        expect(field.querySelector("img")?.getAttribute("src")).toBe("/media/captcha/old.png");
    });
});

describe("bindCaptchaReload", () => {
    it("refreshes the challenge the reload control belongs to", async () => {
        const field = render();
        const fetcher = answering({ imgSrc: "/media/captcha/new.png" });
        bindCaptchaReload(container, fetcher);

        field.querySelector<HTMLButtonElement>("[data-captcha-reload]")?.click();
        await Promise.resolve();
        await Promise.resolve();

        expect(field.querySelector("img")?.getAttribute("src")).toBe("/media/captcha/new.png");
    });

    // The control sits inside a form that posts natively; a reload that submits
    // the sign-in is worse than no reload at all.
    it("does not let the reload submit the form", () => {
        const field = render();
        const fetcher = answering({ imgSrc: "/x.png" });
        bindCaptchaReload(container, fetcher);

        const button = field.querySelector<HTMLButtonElement>("[data-captcha-reload]") as HTMLButtonElement;
        const event = new MouseEvent("click", { bubbles: true, cancelable: true });
        button.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
    });

    it("ignores a click that is not on a reload control", () => {
        render();
        const fetcher = answering({ imgSrc: "/x.png" });
        bindCaptchaReload(container, fetcher);

        container.querySelector("input")?.dispatchEvent(new MouseEvent("click", { bubbles: true }));

        expect(fetcher).not.toHaveBeenCalled();
    });
});
