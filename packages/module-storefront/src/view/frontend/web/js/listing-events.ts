import {
    MutationPhase,
    mutationEvent,
    type FlowEvent,
} from 'mage-obsidian/runtime/mutationEvent.ts';

export const LISTING_DOMAIN = 'listing';

export const ListingOperation = {
    Navigate: 'navigate',
} as const;

export type ListingOperation = (typeof ListingOperation)[keyof typeof ListingOperation];

/** `result` carries the names of the regions that were swapped in. */
export interface ListingNavigateEvent extends FlowEvent<ListingOperation, string[]> {
    url: string;
}

declare module 'mage-obsidian/runtime/eventManager.ts' {
    interface StorefrontEventMap {
        listing_navigate_before: ListingNavigateEvent;
        listing_navigate_after: ListingNavigateEvent;
        listing_navigate_failed: ListingNavigateEvent;
    }
}

export const listingEvent = <Phase extends MutationPhase>(phase: Phase) =>
    mutationEvent(LISTING_DOMAIN, ListingOperation.Navigate, phase);
