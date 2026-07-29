import {
    MutationPhase,
    mutationEvent,
    type FlowEvent,
    type MutationEventName,
} from 'mage-obsidian/runtime/mutationEvent.ts';

export const CHECKOUT_DOMAIN = 'checkout';

export const CHECKOUT_STEP_CHANGE_EVENT = 'checkout_step_change';

export const CheckoutOperation = {
    EstimateShipping: 'estimate_shipping',
    SaveShipping: 'save_shipping',
    ApplyCoupon: 'apply_coupon',
    RemoveCoupon: 'remove_coupon',
    PlaceOrder: 'place_order',
} as const;

export type CheckoutOperation = (typeof CheckoutOperation)[keyof typeof CheckoutOperation];

export interface CheckoutEvent extends FlowEvent<CheckoutOperation> {
    payload?: Record<string, unknown>;
}

export interface CheckoutStepChangeEvent {
    from: string;
    to: string;
}

export type CheckoutEventName = MutationEventName<typeof CHECKOUT_DOMAIN, CheckoutOperation>;

declare module 'mage-obsidian/runtime/eventManager.ts' {
    interface StorefrontEventMap extends Record<CheckoutEventName, CheckoutEvent> {
        [CHECKOUT_STEP_CHANGE_EVENT]: CheckoutStepChangeEvent;
    }
}

export const checkoutEvent = <Phase extends MutationPhase>(
    operation: CheckoutOperation,
    phase: Phase,
) => mutationEvent(CHECKOUT_DOMAIN, operation, phase);
