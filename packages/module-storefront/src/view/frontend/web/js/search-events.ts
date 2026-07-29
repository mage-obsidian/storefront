import {
    MutationPhase,
    mutationEvent,
    type FlowEvent,
    type MutationEventName,
} from 'mage-obsidian/runtime/mutationEvent.ts';

export const SEARCH_DOMAIN = 'search';

export const SEARCH_QUERY_CHANGE_EVENT = 'search_query_change';

export const SearchOperation = {
    Suggest: 'suggest',
} as const;

export type SearchOperation = (typeof SearchOperation)[keyof typeof SearchOperation];

export interface SearchSuggestion {
    title: string;
    num_results?: string | number;
}

export interface SearchQueryEvent {
    query: string;
}

export interface SearchSuggestEvent extends FlowEvent<SearchOperation, SearchSuggestion[]> {
    query: string;
    url: string;
}

export type SearchEventName = MutationEventName<typeof SEARCH_DOMAIN, SearchOperation>;

declare module 'mage-obsidian/runtime/eventManager.ts' {
    interface StorefrontEventMap extends Record<SearchEventName, SearchSuggestEvent> {
        [SEARCH_QUERY_CHANGE_EVENT]: SearchQueryEvent;
    }
}

export const searchEvent = <Phase extends MutationPhase>(phase: Phase) =>
    mutationEvent(SEARCH_DOMAIN, SearchOperation.Suggest, phase);
