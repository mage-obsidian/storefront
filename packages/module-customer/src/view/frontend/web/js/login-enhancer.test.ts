// This file is part of the MageObsidian - Customer project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { beforeEach, describe, expect, it, vi } from "vitest";

const render = (withChallenge: boolean, action = "/customer/account/loginPost"): HTMLFormElement => {
    document.body.innerHTML = `
        <form data-login-form action="${action}"
              data-ajax-login="/customer/ajax/login" data-redirect="/customer/account">
            <div class="field">
                <label class="field__label" for="login-email">Email</label>
                <input class="field__control" id="login-email" name="login[username]" value="">
                <p class="field__error" id="login-email-error"></p>
            </div>
            <div class="field">
                <label class="field__label" for="login-password">Password</label>
                <input class="field__control" id="login-password" type="password" name="login[password]" value="">
                <p class="field__error" id="login-password-error"></p>
            </div>
            ${
                withChallenge
                    ? `<div class="field" data-captcha="user_login">
                           <label class="field__label" for="captcha-user-login">Type this</label>
                           <input class="field__control" id="captcha-user-login" name="captcha[user_login]" value="">
                           <p class="field__error" id="captcha-user-login-error"></p>
                       </div>`
                    : ""
            }
            <label><input type="checkbox" name="persistent_remember_me" value="1"> Remember Me</label>
            <p data-login-error></p>
            <button type="submit">Sign In</button>
        </form>`;
    return document.querySelector("form") as HTMLFormElement;
};

const bodyOf = (fetchMock: ReturnType<typeof vi.fn>): Record<string, unknown> =>
    JSON.parse(fetchMock.mock.calls[0][1].body as string);

const signIn = async (form: HTMLFormElement, word = ""): Promise<void> => {
    form.querySelector<HTMLInputElement>('[name="login[username]"]')!.value = "ada@obsidian.test";
    form.querySelector<HTMLInputElement>('[name="login[password]"]')!.value = "hunter2";
    const challenge = form.querySelector<HTMLInputElement>('[name="captcha[user_login]"]');
    if (challenge) {
        challenge.value = word;
    }
    form.requestSubmit();
    await new Promise((resolve) => setTimeout(resolve, 0));
    await new Promise((resolve) => setTimeout(resolve, 0));
};

beforeEach(() => {
    document.body.innerHTML = "";
    vi.resetModules();
    Object.defineProperty(window, "location", {
        value: { assign: vi.fn(), href: "https://shop.test/customer/account/login/" },
        writable: true,
    });
    globalThis.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ errors: false, redirectUrl: "/customer/account" }),
    });
});

describe("login-enhancer", () => {
    /**
     * The regression this exists for: the ajax endpoint reads the answer from
     * the body, so an enhanced sign-in that does not carry it refuses a shopper
     * the plain form would have let in — and only when the platform is asking
     * for a challenge, which is when nobody is looking.
     */
    it("carries the answered challenge to the ajax endpoint", async () => {
        const form = render(true);
        await import("./login-enhancer.ts");

        await signIn(form, "Mt6pF");

        expect(bodyOf(globalThis.fetch as ReturnType<typeof vi.fn>)).toMatchObject({
            username: "ada@obsidian.test",
            captcha_form_id: "user_login",
            captcha_string: "Mt6pF",
        });
    });

    it("sends an empty answer when the platform is asking for none", async () => {
        const form = render(false);
        await import("./login-enhancer.ts");

        await signIn(form);

        expect(bodyOf(globalThis.fetch as ReturnType<typeof vi.fn>).captcha_string).toBe("");
    });

    it("hands a sign-in that carries a referer to the native form, so Magento sends it back", async () => {
        const form = render(false, "/customer/account/loginPost/referer/aHR0cHM6Ly9zaG9wLnRlc3QvY2hlY2tvdXQv/");
        const submit = vi.spyOn(form, "submit").mockImplementation(() => {});
        await import("./login-enhancer.ts");

        await signIn(form);

        expect(globalThis.fetch).not.toHaveBeenCalled();
        expect(submit).toHaveBeenCalledOnce();
    });

    describe("the checkout context", () => {
        it("is carried to the ajax endpoint when the form says the shopper came from the checkout", async () => {
            const form = render(false);
            form.insertAdjacentHTML("afterbegin", '<input type="hidden" name="context" value="checkout">');
            await import("./login-enhancer.ts");

            await signIn(form);

            expect(String((globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0][0])).toContain("context=checkout");
        });
    });

    describe("remember me", () => {
        it("carries a ticked box to the ajax endpoint", async () => {
            const form = render(false);
            form.querySelector<HTMLInputElement>('[name="persistent_remember_me"]')!.checked = true;
            await import("./login-enhancer.ts");

            await signIn(form);

            expect(bodyOf(globalThis.fetch as ReturnType<typeof vi.fn>).persistent_remember_me).toBe(true);
        });

        it("says nothing when the box is left unticked", async () => {
            const form = render(false);
            await import("./login-enhancer.ts");

            await signIn(form);

            expect(bodyOf(globalThis.fetch as ReturnType<typeof vi.fn>)).not.toHaveProperty("persistent_remember_me");
        });
    });

    describe("the reCAPTCHA of the sign-in form", () => {
        it("sends the token the widget holds to the ajax endpoint", async () => {
            const recaptcha = await import("MageObsidian_Storefront::js/recaptcha");
            recaptcha.reset();
            recaptcha.setToken("tok-1");
            const form = render(false);
            await import("./login-enhancer.ts");

            await signIn(form);

            expect(recaptcha.asked).toEqual(["customer_login"]);
            expect(bodyOf(globalThis.fetch as ReturnType<typeof vi.fn>)["g-recaptcha-response"]).toBe("tok-1");
        });

        it("sends nothing when the form carries no widget", async () => {
            const recaptcha = await import("MageObsidian_Storefront::js/recaptcha");
            recaptcha.reset();
            const form = render(false);
            await import("./login-enhancer.ts");

            await signIn(form);

            expect(bodyOf(globalThis.fetch as ReturnType<typeof vi.fn>)).not.toHaveProperty("g-recaptcha-response");
        });

        it("asks for a fresh token once the sign-in is refused, since a token is good for one request", async () => {
            const recaptcha = await import("MageObsidian_Storefront::js/recaptcha");
            recaptcha.reset();
            recaptcha.setToken("tok-1");
            globalThis.fetch = vi.fn().mockResolvedValue({
                ok: true,
                json: async () => ({ errors: true, message: "Invalid login or password." }),
            });
            const form = render(false);
            await import("./login-enhancer.ts");

            await signIn(form);

            expect(recaptcha.resets).toEqual(["customer_login"]);
        });
    });

    describe("the challenge that appears once failures pile up", () => {
        const CHALLENGE_PAGE = `
            <form data-login-form>
                <div class="field-captcha" data-captcha="user_login" data-captcha-refresh="/captcha/refresh">
                    <input name="captcha[user_login]" value="">
                    <img data-captcha-image src="/media/captcha/abc.png" alt="">
                </div>
            </form>`;

        const rejectedSignIn = (): ReturnType<typeof vi.fn> =>
            vi.fn().mockImplementation(async (url: string) =>
                url.includes("/customer/ajax/login")
                    ? { ok: true, json: async () => ({ errors: true, message: "Invalid login or password." }) }
                    : { ok: true, text: async () => CHALLENGE_PAGE },
            );

        const prepare = async (required: boolean | null, withChallenge = false) => {
            const customerData = await import("MageObsidian_ModernFrontend::js/customer-data");
            customerData.__reset();
            if (required !== null) {
                customerData.__setSection("captcha", { user_login: { isRequired: required } });
            }
            globalThis.fetch = rejectedSignIn();
            const form = render(withChallenge);
            await import("./login-enhancer.ts");
            return { form, customerData, fetchMock: globalThis.fetch as ReturnType<typeof vi.fn> };
        };

        const pagesFetched = (fetchMock: ReturnType<typeof vi.fn>): string[] =>
            fetchMock.mock.calls.map(([url]) => String(url)).filter((url) => !url.includes("/customer/ajax/login"));

        it("asks for the challenge state when a sign-in is refused and shows the field once it is required", async () => {
            const { form, customerData } = await prepare(true);

            await signIn(form);

            expect(customerData.reload.calls).toEqual([[["captcha"]]]);
            const field = form.querySelector('[data-captcha="user_login"]');
            expect(field).not.toBeNull();
            expect(field!.nextElementSibling?.tagName).toBe("BUTTON");
        });

        it("leaves the form alone while the platform does not ask for a challenge", async () => {
            const { form, fetchMock } = await prepare(false);

            await signIn(form);

            expect(form.querySelector("[data-captcha]")).toBeNull();
            expect(pagesFetched(fetchMock)).toEqual([]);
        });

        it("does not fetch the page again when the challenge is already there", async () => {
            const { form, customerData, fetchMock } = await prepare(true, true);

            await signIn(form, "wrong");

            expect(form.querySelectorAll("[data-captcha]").length).toBe(1);
            expect(customerData.reload.calls).toEqual([]);
            expect(pagesFetched(fetchMock)).toEqual([]);
        });

        it("keeps the refusal message on screen next to the new field", async () => {
            const { form } = await prepare(true);

            await signIn(form);

            expect(form.querySelector("[data-login-error]")!.textContent).toBe("Invalid login or password.");
        });
    });
});
