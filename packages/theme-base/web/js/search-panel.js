import {createApp, ref, watch, nextTick} from 'vue';
import {pinia, useMainStore} from 'Theme::js/store/main';
import {onClickOutside} from 'Theme::js/lib/mini-vueuse';

const app = createApp({
    setup() {
        const mainStore = useMainStore();
        const searchInput = ref(null);
        const root = ref(null);
        watch(
            () => mainStore.searchOpen,
            (newValue) => {
                if (newValue) {
                    nextTick(() => {
                        if (searchInput.value) {
                            searchInput.value.focus();
                        }
                    });
                }
            }
        );

        onClickOutside(root, (event) => {
            if (mainStore.searchOpen) {
                const insideRoot = root.value && root.value.contains(event.target);
                const insideSearchlink = mainStore.searchLink && mainStore.searchLink.contains(event.target);
                if (!insideRoot && !insideSearchlink
                ) {
                    mainStore.searchOpen = false;
                }
            }
        });

        return {
            mainStore,
            searchInput,
            root
        };
    },
});

app.use(pinia);

app.mount('#search-panel');
