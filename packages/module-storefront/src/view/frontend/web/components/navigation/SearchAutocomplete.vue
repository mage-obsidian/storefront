<script setup lang="ts">
import { ref, computed, onBeforeUnmount, nextTick, useId, watch } from "vue";
import Icon from "MageObsidian_ModernFrontend::elements/Icon";
import events from "MageObsidian_ModernFrontend::js/events";
import { MutationPhase } from "mage-obsidian/runtime/mutationEvent.ts";
import {
    SEARCH_QUERY_CHANGE_EVENT,
    SearchOperation,
    searchEvent,
    type SearchSuggestion,
} from "MageObsidian_Storefront::js/search-events";

// Header quick-search. An icon button toggles a panel holding a real
// <form method="get"> that submits to the native search results URL — so search
// works the moment the markup exists (the Twig template also ships a <noscript>
// form for the no-JS path). On top of that the island adds an accessible
// combobox: as you type past minLength it fetches Magento's native
// search/ajax/suggest endpoint (term suggestions: {title, num_results}) and
// exposes them as a listbox with full keyboard support. Choosing a suggestion
// runs that search; pressing Enter with no active option submits the form.
interface SearchLabels {
    search?: string;
    placeholder?: string;
    submit?: string;
    close?: string;
    results?: string;
}

const props = withDefaults(
    defineProps<{
        actionUrl: string;
        queryParam?: string;
        queryValue?: string;
        suggestUrl?: string;
        minLength?: number;
        maxLength?: number;
        suggestionsEnabled?: boolean;
        labels?: SearchLabels;
    }>(),
    {
        queryParam: "q",
        queryValue: "",
        suggestUrl: "",
        minLength: 3,
        maxLength: 128,
        suggestionsEnabled: true,
        labels: () => ({}),
    },
);

const text = computed(() => ({
    search: props.labels.search ?? "Search",
    placeholder: props.labels.placeholder ?? "Search the store",
    submit: props.labels.submit ?? "Search",
    close: props.labels.close ?? "Close search",
    results: props.labels.results ?? "Search suggestions",
}));

const open = ref(false);
const term = ref(props.queryValue);
const suggestions = ref<SearchSuggestion[]>([]);
const activeIndex = ref(-1);

const root = ref<HTMLElement | null>(null);
const trigger = ref<HTMLElement | null>(null);
const input = ref<HTMLInputElement | null>(null);
const listboxId = useId();

let debounce: ReturnType<typeof setTimeout> | undefined;
let requestToken = 0;

const showList = computed(() => open.value && suggestions.value.length > 0);
const optionId = (index: number): string => `${listboxId}-opt-${index}`;
const activeId = computed(() => (activeIndex.value >= 0 ? optionId(activeIndex.value) : undefined));

const suggestUrlFor = (query: string): string => {
    const sep = props.suggestUrl.includes("?") ? "&" : "?";
    return `${props.suggestUrl}${sep}${props.queryParam}=${encodeURIComponent(query)}`;
};

const resultUrlFor = (query: string): string => {
    const sep = props.actionUrl.includes("?") ? "&" : "?";
    return `${props.actionUrl}${sep}${props.queryParam}=${encodeURIComponent(query)}`;
};

async function fetchSuggestions(query: string): Promise<void> {
    if (!props.suggestionsEnabled || !props.suggestUrl || query.length < props.minLength) {
        suggestions.value = [];
        activeIndex.value = -1;
        return;
    }

    const request = await events.dispatch(searchEvent(MutationPhase.Before), {
        operation: SearchOperation.Suggest,
        cancelled: false,
        query,
        url: suggestUrlFor(query),
    });
    if (request.cancelled) {
        return;
    }

    const token = ++requestToken;
    let result: SearchSuggestion[] = [];
    let failed = false;
    try {
        const response = await fetch(request.url, {
            headers: { "X-Requested-With": "XMLHttpRequest" },
        });
        if (!response.ok) {
            failed = true;
        } else {
            const data = (await response.json()) as SearchSuggestion[];
            result = Array.isArray(data) ? data.filter((item) => item && item.title) : [];
        }
    } catch {
        failed = true;
    }

    // Ignore a stale response that resolved after a newer keystroke.
    if (token === requestToken) {
        suggestions.value = failed ? [] : result;
        activeIndex.value = -1;
    }

    await events.dispatch(searchEvent(MutationPhase.After), { ...request, result });
    if (failed) {
        await events.dispatch(searchEvent(MutationPhase.Failed), { ...request, result });
    }
}

watch(term, (value) => {
    const query = value.trim();
    void events.dispatch(SEARCH_QUERY_CHANGE_EVENT, { query }, { mirror: false });
    clearTimeout(debounce);
    debounce = setTimeout(() => fetchSuggestions(query), 200);
});

const onDocumentClick = (event: Event): void => {
    if (root.value && !root.value.contains(event.target as Node | null)) {
        close(false);
    }
};

function openPanel(): void {
    open.value = true;
    document.addEventListener("click", onDocumentClick, true);
    nextTick(() => input.value?.focus());
}

function close(returnFocus = true): void {
    if (!open.value) {
        return;
    }
    open.value = false;
    activeIndex.value = -1;
    document.removeEventListener("click", onDocumentClick, true);
    if (returnFocus) {
        trigger.value?.focus();
    }
}

const toggle = (): void => (open.value ? close(false) : openPanel());

function move(step: number): void {
    if (suggestions.value.length === 0) {
        return;
    }
    const count = suggestions.value.length;
    activeIndex.value = (activeIndex.value + step + count) % count;
}

function selectSuggestion(suggestion: SearchSuggestion): void {
    window.location.href = resultUrlFor(suggestion.title);
}

// Enter on an active option runs that suggestion; otherwise let the form submit
// the typed term natively (return true so @keydown.enter does not preventDefault).
function onEnter(event: KeyboardEvent): void {
    if (activeIndex.value >= 0 && suggestions.value[activeIndex.value]) {
        event.preventDefault();
        selectSuggestion(suggestions.value[activeIndex.value]);
    }
}

onBeforeUnmount(() => {
    clearTimeout(debounce);
    document.removeEventListener("click", onDocumentClick, true);
});

defineExpose({ suggestions, activeIndex, open, fetchSuggestions });
</script>

<template>
    <div ref="root" class="relative" @keydown.escape.prevent="close()">
        <button
            ref="trigger"
            type="button"
            class="inline-flex items-center transition-colors hover:text-ink"
            :aria-label="text.search"
            :aria-expanded="open ? 'true' : 'false'"
            @click="toggle"
        >
            <Icon name="magnifying-glass" set="outline" class="h-5 w-5" />
        </button>

        <div
            v-if="open"
            class="absolute right-0 z-40 mt-3 w-[min(22rem,calc(100vw-2rem))] rounded-edge border border-ash-200 bg-alabaster/95 p-3 shadow-xl backdrop-blur-md"
        >
            <form :action="actionUrl" method="get" role="search" class="flex items-center gap-2">
                <input
                    ref="input"
                    v-model="term"
                    type="text"
                    :name="queryParam"
                    :maxlength="maxLength"
                    :placeholder="text.placeholder"
                    autocomplete="off"
                    role="combobox"
                    aria-autocomplete="list"
                    :aria-controls="listboxId"
                    :aria-expanded="showList ? 'true' : 'false'"
                    :aria-activedescendant="activeId"
                    :aria-label="text.search"
                    class="w-full rounded-edge border border-ash-300 bg-transparent px-3 py-2 font-mono text-sm text-ink focus:border-ink focus:outline-none"
                    @keydown.down.prevent="move(1)"
                    @keydown.up.prevent="move(-1)"
                    @keydown.enter="onEnter"
                >
                <button
                    type="submit"
                    :aria-label="text.submit"
                    class="inline-flex shrink-0 items-center justify-center rounded-edge border border-ink bg-ink p-2 text-alabaster transition-colors hover:bg-transparent hover:text-ink"
                >
                    <Icon name="magnifying-glass" set="outline" class="h-4 w-4" />
                </button>
            </form>

            <ul
                v-show="showList"
                :id="listboxId"
                role="listbox"
                :aria-label="text.results"
                class="mt-2 max-h-80 overflow-y-auto"
            >
                <li
                    v-for="(suggestion, index) in suggestions"
                    :id="optionId(index)"
                    :key="suggestion.title"
                    role="option"
                    :aria-selected="index === activeIndex ? 'true' : 'false'"
                    class="flex cursor-pointer items-center justify-between gap-3 rounded-edge px-3 py-2 font-mono text-sm transition-colors"
                    :class="index === activeIndex ? 'bg-ash-100 text-ink' : 'text-ink-soft hover:bg-ash-100 hover:text-ink'"
                    @mousedown.prevent="selectSuggestion(suggestion)"
                    @mousemove="activeIndex = index"
                >
                    <span class="truncate">{{ suggestion.title }}</span>
                    <span v-if="suggestion.num_results" class="shrink-0 text-[0.7rem] text-ash-500">{{ suggestion.num_results }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>
