import { defineStore, createPinia } from '#/pinia';

const pinia = createPinia(); // Crea la instancia de Pinia

const useMainStore = defineStore('main', {
    state: () => ({
        user: null,
        isAuthenticated: false,
        theme: 'light',
    }),
    getters: {
        isUserLoggedIn: (state) => !!state.user,
        themePreference: (state) => state.theme,
    },
    actions: {
        login(user) {
            this.user = user;
            this.isAuthenticated = true;
        },
        logout() {
            this.user = null;
            this.isAuthenticated = false;
        },
        toggleTheme() {
            this.theme = this.theme === 'light' ? 'dark' : 'light';
        },
    },
});

// Exporta el store y la instancia de Pinia
export { pinia, useMainStore };
