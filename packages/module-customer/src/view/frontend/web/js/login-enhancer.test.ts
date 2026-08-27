import { beforeEach, describe, expect, it, vi } from "vitest";

const render = (withChallenge: boolean): HTMLFormElement => {
    document.body.innerHTML = `
        <form data-login-form action="/customer/account/loginPost"
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
                    ? `<div class="field">
                           <label class="field__label" for="captcha-user-login">Type this</label>
                           <input class="field__control" id="captcha-user-login" name="captcha[user_login]" value="">
                           <p class="field__error" id="captcha-user-login-error"></p>
                       </div>`
                    : ""
            }
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
    Object.defineProperty(window, "location", { value: { assign: vi.fn() }, writable: true });
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
});
