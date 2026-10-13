<!--
 This file is part of the MageObsidian - Checkout project.

 SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 SPDX-License-Identifier: MIT
-->
<script setup lang="ts">
import { ref, watch } from "vue";
import Field from "MageObsidian_Storefront::form/Field";
import { isValidEmail } from "MageObsidian_Storefront::js/form-validation";
import { useCheckout } from "MageObsidian_Checkout::js/useCheckout";

// First step of the guest flow: collect the email and continue. It runs Magento's
// native availability check (customers/isEmailAvailable) — if an account already
// exists, it offers to sign in and still lets the shopper continue as a guest, as
// Luma does. Logged-in shoppers never reach this step (the store seeds straight
// to shipping).
interface IdentificationLabels {
    heading?: string;
    intro?: string;
    email?: string;
    invalidEmail?: string;
    continue?: string;
    hasAccount?: string;
    accountExists?: string;
    signIn?: string;
    continueAsGuest?: string;
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
watch(() => checkout.email, (value) => {
    email.value = value;
});
const emailError = ref("");
const accountExists = ref(false);
const checking = ref(false);


async function submit(): Promise<void> {
    emailError.value = "";
    accountExists.value = false;
    const value = email.value.trim();
    if (!isValidEmail(value)) {
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

function continueAsGuest(): void {
    checkout.setEmail(email.value.trim());
}

// One-page commit: silently sync a valid email into the store on blur (no error
// nag mid-typing). Invalid/empty is left for the shopper to finish.
async function syncEmail(): Promise<void> {
    const value = email.value.trim();
    if (!isValidEmail(value)) {
        return;
    }
    accountExists.value = !(await checkout.checkEmailAvailable(value));
    checkout.setEmail(value);
}

defineExpose({ submit });
</script>

<template>
    <form class="flex max-w-lg flex-col gap-5" novalidate @submit.prevent="submit">
        <p class="text-ink-soft">{{ t("intro", "Enter your email to continue as a guest.") }}</p>

        <p v-if="loginUrl" data-signin-prompt class="text-sm text-ink-soft">
            {{ t("hasAccount", "Already have an account?") }}
            <a :href="loginUrl" class="link link--meta">{{ t("signIn", "Sign in") }}</a>
        </p>

        <Field
            id="checkout-email"
            v-model="email"
            :label="t('email', 'Email address')"
            type="email"
            required
            autocomplete="email"
            :error="emailError"
            @blur="hideAdvance ? syncEmail() : undefined"
        />

        <div v-if="accountExists" data-account-exists role="status" class="flex flex-col gap-3 rounded-edge border border-ash-300 bg-alabaster p-4 text-sm text-ink-soft">
            <p>{{ t("accountExists", "You already have an account with us. Sign in or continue as guest.") }}</p>
            <div class="flex flex-wrap items-center gap-4">
                <a v-if="loginUrl" :href="loginUrl" class="font-mono text-xs uppercase tracking-eyebrow text-ink underline">{{ t("signIn", "Sign in") }}</a>
                <button
                    v-if="!hideAdvance"
                    type="button"
                    data-continue-as-guest
                    class="font-mono text-xs uppercase tracking-eyebrow text-ink underline"
                    @click="continueAsGuest"
                >
                    {{ t("continueAsGuest", "Continue as guest") }}
                </button>
            </div>
        </div>

        <button
            v-if="!hideAdvance"
            type="submit"
            :disabled="checking"
            class="checkout-cta btn btn--solid btn--lg btn--block lg:w-fit"
        >
            {{ t("continue", "Continue") }}
        </button>
    </form>
</template>
