import { useCustomerData } from "MageObsidian_ModernFrontend::js/customer-data";

export interface GateCartSection {
    isGuestCheckoutAllowed?: boolean;
}

export interface GateCustomerSection {
    firstname?: string;
}

const REFERER_ALPHABET: Record<string, string> = { "+": "-", "/": "_", "=": "~" };

export function requiresSignIn(
    cart: GateCartSection | null | undefined,
    customer: GateCustomerSection | null | undefined,
): boolean {
    return cart?.isGuestCheckoutAllowed === false && !customer?.firstname;
}

export function encodeReferer(url: string): string {
    return btoa(url).replace(/[+/=]/g, (char) => REFERER_ALPHABET[char]);
}

export function signInUrl(loginUrl: string, target: string): string {
    return `${loginUrl.replace(/\/?$/, "/")}referer/${encodeReferer(target)}/`;
}

export function checkoutDestination(checkoutUrl: string, loginUrl: string | undefined): string {
    if (!loginUrl) {
        return checkoutUrl;
    }
    const customerData = useCustomerData();
    const cart = customerData.section("cart") as GateCartSection | null;
    const customer = customerData.section("customer") as GateCustomerSection | null;
    return requiresSignIn(cart, customer) ? signInUrl(loginUrl, checkoutUrl) : checkoutUrl;
}
