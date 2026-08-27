/**
 * The storefront half of an administrator assisting a customer.
 *
 * When a store switches "Login as Customer" on, an administrator can be browsing
 * inside a real customer's session. The customer whose account it is has a right
 * to know when that is happening, and the administrator needs a way out that is
 * not the customer's own sign-out — so the banner is not decoration, it is the
 * only thing on the page that says whose session this is.
 *
 * The data is per session, so it comes from the platform's private content
 * rather than from the page: the markup ships empty on every page, cacheable,
 * and stays hidden until the sections say otherwise.
 */
import events from "MageObsidian_ModernFrontend::js/events";
import { LifecycleEvent } from "mage-obsidian/runtime/lifecycleEvents.ts";

export interface AssistedSession {
    adminUserId?: number | string;
    websiteName?: string;
}

const BANNER = "[data-assisted-session]";
const SECTION = "loggedAsCustomer";
const CUSTOMER_SECTION = "customer";

export function noticeFor(session: AssistedSession, fullname: string, template: string): string {
    return template.replace("%1", fullname).replace("%2", session.websiteName ?? "");
}

const isAssisted = (session: AssistedSession | undefined): boolean =>
    Boolean(session && session.adminUserId !== undefined && session.adminUserId !== null && session.adminUserId !== "");

/**
 * @returns whether the banner was shown, so a caller can tell "nobody is
 * assisting" from "the sections have not arrived yet".
 */
export function renderNotice(
    banner: HTMLElement,
    session: AssistedSession | undefined,
    customer: { fullname?: string } | undefined,
): boolean {
    if (!isAssisted(session)) {
        banner.hidden = true;
        return false;
    }

    const text = banner.querySelector("[data-assisted-session-text]");
    if (text) {
        text.textContent = noticeFor(
            session as AssistedSession,
            customer?.fullname ?? "",
            banner.getAttribute("data-assisted-session") || "You are connected as %1 on %2",
        );
    }
    banner.hidden = false;
    return true;
}

export async function watchAssistedSession(root: ParentNode = document): Promise<void> {
    const banner = root.querySelector<HTMLElement>(BANNER);
    if (!banner) {
        return;
    }

    const { useCustomerData } = await import("MageObsidian_ModernFrontend::js/customer-data");
    const customerData = useCustomerData();

    const paint = (): void => {
        renderNotice(
            banner,
            customerData.section(SECTION) as AssistedSession | undefined,
            customerData.section(CUSTOMER_SECTION) as { fullname?: string } | undefined,
        );
    };

    paint();
    // The banner ships empty on a cacheable page, so the sections arriving is
    // what decides whether it is ever shown.
    events.observe(LifecycleEvent.SectionReloadAfter, async () => paint());
}

void watchAssistedSession();
