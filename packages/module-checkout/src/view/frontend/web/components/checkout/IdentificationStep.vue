<script setup lang="ts">
import { ref } from "vue";
import { useCheckout } from "MageObsidian_Checkout::js/useCheckout";

// First step of the guest flow: collect the email and continue. It runs Magento's
// native availability check (customers/isEmailAvailable) — if an account already
// exists, guest checkout would be rejected at order placement, so we surface a
// sign-in link and block continuing instead of failing late. Logged-in shoppers
// never reach this step (the store seeds straight to shipping).
interface IdentificationLabels {
    heading?: string;
    intro?: string;
    email?: string;
    invalidEmail?: string;
    continue?: string;
    accountExists?: string;
    signIn?: string;
}

const props = withDefaults(
    defineProps<{
        loginUrl?: string;
        labels?: IdentificationLabels;
        // In one-page mode there is no "Continue" button; the email is committed
        // on blur so the reactive shipping/payment orchestration can proceed.
        hideAdvance?: boolean;
    }>(),
    { loginUrl: "", labels: () => ({}), hideAdvance: false },
);

const checkout = useCheckout();

const t = (key: keyof IdentificationLabels, fallback: string): string => props.labels?.[key] ?? fallback;

const email = ref(checkout.email);
const emailError = ref("");
const accountExists = ref(false);
const checking = ref(false);

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

async function submit(): Promise<void> {
    emailError.value = "";
    accountExists.value = false;
    const value = email.value.trim();
    if (!EMAIL_RE.test(value)) {
        emailError.value = t("invalidEmail", "Please enter a valid email address.");
        return;
    }
    checking.value = true;
    try {
        const available = await checkout.checkEmailAvailable(value);
        if (!available) {
            accountExists.value = true;
            return;
        }
        checkout.setEmail(value);
    } finally {
        checking.value = false;
    }
}

// One-page commit: silently sync a valid email into the store on blur (no error
// nag mid-typing). Invalid/empty is left for the shopper to finish.
async function syncEmail(): Promise<void> {
    const value = email.value.trim();
    if (!EMAIL_RE.test(value)) {
        return;
    }
    accountExists.value = !(await checkout.checkEmailAvailable(value));
    if (!accountExists.value) {
        checkout.setEmail(value);
    }
}

defineExpose({ submit });
</script>

<template>
    <form class="flex max-w-lg flex-col gap-5" novalidate @submit.prevent="submit">
        <p class="text-ink-soft">{{ t("intro", "Enter your email to continue as a guest.") }}</p>

        <div class="flex flex-col gap-1">
            <label for="checkout-email" class="font-mono text-[0.68rem] uppercase tracking-[0.16em] text-ink-soft">
                {{ t("email", "Email address") }}
            </label>
            <input
                id="checkout-email"
                v-model="email"
                type="email"
                autocomplete="email"
                :aria-invalid="emailError ? 'true' : undefined"
                :aria-describedby="emailError ? 'checkout-email-error' : undefined"
                class="rounded-edge border border-ash-300 bg-transparent px-3 py-2.5 font-mono text-sm text-ink focus:border-ink focus:outline-none"
                @blur="hideAdvance ? syncEmail() : undefined"
            >
            <p v-if="emailError" id="checkout-email-error" role="alert" class="font-mono text-[0.66rem] text-sale">{{ emailError }}</p>
        </div>

        <p v-if="accountExists" role="alert" class="rounded-edge border border-ash-300 bg-alabaster p-4 text-sm text-ink-soft">
            {{ t("accountExists", "An account already exists with this email.") }}
            <a v-if="loginUrl" :href="loginUrl" class="font-mono text-xs uppercase tracking-[0.14em] text-ink underline">{{ t("signIn", "Sign in") }}</a>
        </p>

        <button
            v-if="!hideAdvance"
            type="submit"
            :disabled="checking"
            class="inline-flex w-fit items-center justify-center rounded-edge border border-ink bg-ink px-8 py-3 font-mono text-[0.72rem] uppercase tracking-[0.18em] text-alabaster transition-colors hover:bg-transparent hover:text-ink disabled:opacity-50"
        >
            {{ t("continue", "Continue") }}
        </button>
    </form>
</template>
