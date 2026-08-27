/**
 * The foundation's reCAPTCHA runtime, stubbed so this repo tests itself without
 * the sibling — and without the vendor's script. `token` is what `tokenFor`
 * answers; `mounted` records the elements the review step handed over.
 */
export const mounted: HTMLElement[] = [];
export let token: string | null = null;

export const setToken = (value: string | null): void => {
    token = value;
};

export const reset = (): void => {
    mounted.length = 0;
    token = null;
};

export const mountReCaptcha = async (element: HTMLElement): Promise<null> => {
    mounted.push(element);
    return null;
};

export const tokenFor = async (): Promise<string | null> => token;
