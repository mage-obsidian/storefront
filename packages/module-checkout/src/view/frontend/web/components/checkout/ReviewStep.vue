<script setup lang="ts">
import { computed, ref } from "vue";
import { useCheckout } from "MageObsidian_Checkout::js/useCheckout";
import Agreements from "MageObsidian_Checkout::checkout/Agreements";

interface ReviewLabels {
    couponHeading?: string;
    couponPlaceholder?: string;
    apply?: string;
    remove?: string;
    couponApplied?: string;
    recapHeading?: string;
    email?: string;
    shipping?: string;
    payment?: string;
    placeOrder?: string;
    placing?: string;
    confirmingShipping?: string;
}

const props = withDefaults(defineProps<{ labels?: ReviewLabels }>(), { labels: () => ({}) });

const checkout = useCheckout();
const code = ref("");
const couponBusy = ref(false);

const t = (key: keyof ReviewLabels, fallback: string): string => props.labels?.[key] ?? fallback;

async function withCouponBusy(run: () => Promise<unknown>): Promise<void> {
    if (couponBusy.value) {
        return;
    }
    couponBusy.value = true;
    try {
        await run();
    } finally {
        couponBusy.value = false;
    }
}

async function apply(): Promise<void> {
    await withCouponBusy(async () => {
        if (await checkout.applyCoupon(code.value)) {
            code.value = "";
        }
    });
}

function remove(): Promise<void> {
    return withCouponBusy(() => checkout.removeCoupon());
}

const shippingPending = computed(() => checkout.shippingDirty || checkout.savingShipping);

const paymentTitle = (): string =>
    checkout.paymentMethods.find((m) => m.code === checkout.selectedPayment)?.title ?? checkout.selectedPayment;
</script>

<template>
    <div class="flex flex-col gap-10">
        <section aria-labelledby="coupon-heading">
            <h3 id="coupon-heading" class="mb-4 font-mono text-xs uppercase tracking-[0.16em] text-ink-soft">
                {{ t("couponHeading", "Discount code") }}
            </h3>
            <div v-if="checkout.appliedCoupon" class="flex items-center justify-between gap-4 rounded-edge border border-ash-300 bg-alabaster-raised px-4 py-3">
                <span class="font-mono text-sm text-ink">{{ t("couponApplied", "Applied") }}: {{ checkout.appliedCoupon }}</span>
                <button
                    type="button"
                    :disabled="couponBusy"
                    :aria-busy="couponBusy ? 'true' : undefined"
                    class="font-mono text-[0.68rem] uppercase tracking-[0.14em] text-sale underline disabled:opacity-50"
                    @click="remove"
                >
                    {{ t("remove", "Remove") }}
                </button>
            </div>
            <form v-else class="flex items-start gap-3" @submit.prevent="apply">
                <div class="flex flex-1 flex-col gap-1">
                    <label for="coupon-code" class="sr-only">{{ t("couponHeading", "Discount code") }}</label>
                    <input
                        id="coupon-code"
                        v-model="code"
                        type="text"
                        :placeholder="t('couponPlaceholder', 'Enter code')"
                        :aria-invalid="checkout.couponError ? 'true' : undefined"
                        :aria-describedby="checkout.couponError ? 'coupon-error' : undefined"
                        class="field__control w-full font-mono"
                    >
                    <p v-if="checkout.couponError" id="coupon-error" role="alert" class="font-mono text-[0.66rem] text-sale">{{ checkout.couponError }}</p>
                </div>
                <button
                    type="submit"
                    :disabled="couponBusy"
                    :class="['btn btn--outline shrink-0', couponBusy && 'is-loading']"
                >
                    <span class="btn__label">{{ t("apply", "Apply") }}</span>
                    <span v-if="couponBusy" class="btn__spinner" aria-hidden="true"></span>
                </button>
            </form>
        </section>

        <section aria-labelledby="recap-heading">
            <h3 id="recap-heading" class="mb-4 font-mono text-xs uppercase tracking-[0.16em] text-ink-soft">
                {{ t("recapHeading", "Review") }}
            </h3>
            <dl class="flex flex-col gap-2 text-sm text-ink-soft">
                <div class="flex gap-2">
                    <dt class="w-24 shrink-0 font-mono text-[0.68rem] uppercase tracking-[0.14em]">{{ t("email", "Email") }}</dt>
                    <dd class="text-ink">{{ checkout.email }}</dd>
                </div>
                <div v-if="checkout.selectedMethod" class="flex gap-2">
                    <dt class="w-24 shrink-0 font-mono text-[0.68rem] uppercase tracking-[0.14em]">{{ t("shipping", "Shipping") }}</dt>
                    <dd class="text-ink">{{ checkout.selectedMethod.carrier_title }}<span v-if="checkout.selectedMethod.method_title"> — {{ checkout.selectedMethod.method_title }}</span></dd>
                </div>
                <div v-if="checkout.selectedPayment" class="flex gap-2">
                    <dt class="w-24 shrink-0 font-mono text-[0.68rem] uppercase tracking-[0.14em]">{{ t("payment", "Payment") }}</dt>
                    <dd class="text-ink">{{ paymentTitle() }}</dd>
                </div>
            </dl>
        </section>

        <Agreements />

        <div class="flex flex-col gap-3">
            <button
                type="button"
                data-place-order
                :disabled="checkout.placingOrder || shippingPending || !checkout.selectedPayment || !checkout.allRequiredAccepted"
                class="btn btn--solid btn--lg w-fit px-10 py-3.5"
                @click="checkout.placeOrder()"
            >
                {{ checkout.placingOrder ? t("placing", "Placing order…") : t("placeOrder", "Place order") }}
            </button>
            <p v-if="shippingPending" data-shipping-pending aria-live="polite" class="font-mono text-xs text-ink-soft">
                {{ t("confirmingShipping", "Confirming your shipping details…") }}
            </p>
        </div>

        <p v-if="checkout.orderError" role="alert" class="font-mono text-sm text-sale">{{ checkout.orderError }}</p>
    </div>
</template>
