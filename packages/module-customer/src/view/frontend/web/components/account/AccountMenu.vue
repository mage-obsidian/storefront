<script setup lang="ts">
import { computed, ref, onBeforeUnmount, nextTick, useId } from "vue";
import { useCustomerData } from "MageObsidian_ModernFrontend::js/customer-data";

// Header account state. Reads the engine's customer-data `customer` section, so
// it reflects sign-in/out reactively and stays FPC-safe (the name is never baked
// into cached HTML). Guests get a plain "Sign In" link; signed-in customers get
// a disclosure (a button toggling a list of account links) — intentionally not
// an ARIA menu widget, since the items are navigation links. A <noscript> link in
// the Twig is the no-JS fallback. Mirrors the Switcher disclosure a11y pattern.

interface CustomerSection {
    firstname?: string;
    fullname?: string;
}

interface AccountLinks {
    label: string;
    url: string;
}

const props = withDefaults(
    defineProps<{
        accountUrl?: string;
        loginUrl?: string;
        logoutUrl?: string;
        ordersUrl?: string;
        addressesUrl?: string;
        // i18n labels (resolved in Twig).
        signInLabel?: string;
        menuLabel?: string;
        myAccountLabel?: string;
        myOrdersLabel?: string;
        addressBookLabel?: string;
        signOutLabel?: string;
    }>(),
    {
        accountUrl: "",
        loginUrl: "",
        logoutUrl: "",
        ordersUrl: "",
        addressesUrl: "",
        signInLabel: "Sign In",
        menuLabel: "Account menu",
        myAccountLabel: "My Account",
        myOrdersLabel: "My Orders",
        addressBookLabel: "Address Book",
        signOutLabel: "Sign Out",
    },
);

const customerData = useCustomerData();
const customer = computed<CustomerSection>(() => (customerData.section("customer") ?? {}) as CustomerSection);
const isLoggedIn = computed(() => Boolean(customer.value.firstname || customer.value.fullname));
const displayName = computed(() => customer.value.firstname || props.menuLabel);

const links = computed<AccountLinks[]>(() => [
    { label: props.myAccountLabel, url: props.accountUrl },
    { label: props.myOrdersLabel, url: props.ordersUrl },
    { label: props.addressBookLabel, url: props.addressesUrl },
]);

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const trigger = ref<HTMLElement | null>(null);
const panel = ref<HTMLElement | null>(null);
const panelId = useId();

const onDocumentClick = (event: Event): void => {
    if (root.value && !root.value.contains(event.target as Node | null)) {
        close(false);
    }
};

const openPanel = (): void => {
    open.value = true;
    document.addEventListener("click", onDocumentClick, true);
    nextTick(() => panel.value?.querySelector("a")?.focus());
};

const close = (returnFocus = true): void => {
    if (!open.value) {
        return;
    }
    open.value = false;
    document.removeEventListener("click", onDocumentClick, true);
    if (returnFocus) {
        trigger.value?.focus();
    }
};

const toggle = (): void => (open.value ? close(false) : openPanel());

onBeforeUnmount(() => document.removeEventListener("click", onDocumentClick, true));
</script>

<template>
    <a
        v-if="!isLoggedIn"
        :href="loginUrl"
        class="hidden transition-colors hover:text-ink sm:inline"
    >{{ signInLabel }}</a>

    <!-- max-sm:hidden (not `hidden sm:block`): in Tailwind v4 the display-utility
         sort order lets `hidden` win over `sm:block`, so the disclosure would stay
         hidden on desktop. A block-by-default div hidden below sm avoids that. -->
    <div v-else ref="root" class="relative max-sm:hidden" @keydown.escape="close()">
        <button
            ref="trigger"
            type="button"
            class="inline-flex items-center gap-1 font-mono text-[0.72rem] uppercase tracking-[0.12em] text-ink-soft transition-colors hover:text-ink"
            aria-haspopup="true"
            :aria-controls="panelId"
            :aria-label="`${menuLabel} — ${displayName}`"
            :aria-expanded="open ? 'true' : 'false'"
            @click="toggle"
        >
            {{ displayName }}
            <svg class="h-3 w-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <ul
            v-if="open"
            :id="panelId"
            ref="panel"
            :aria-label="menuLabel"
            class="absolute right-0 z-40 mt-2 min-w-[10rem] rounded-edge border border-ash-200 bg-alabaster/95 py-1 shadow-xl backdrop-blur-md"
        >
            <li v-for="link in links" :key="link.label">
                <a
                    :href="link.url"
                    class="block px-4 py-2 font-mono text-[0.72rem] uppercase tracking-[0.12em] text-ink-soft transition-colors hover:bg-ash-100 hover:text-ink"
                    @click="close(false)"
                >{{ link.label }}</a>
            </li>
            <li class="mt-1 border-t border-ash-200 pt-1">
                <a
                    :href="logoutUrl"
                    class="block px-4 py-2 font-mono text-[0.72rem] uppercase tracking-[0.12em] text-ink-soft transition-colors hover:bg-ash-100 hover:text-ink"
                    @click="close(false)"
                >{{ signOutLabel }}</a>
            </li>
        </ul>
    </div>
</template>
