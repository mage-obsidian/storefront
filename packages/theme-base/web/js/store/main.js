import {defineStore, createPinia} from '#/pinia';

const pinia = createPinia(); // Crea la instancia de Pinia

const useMainStore = defineStore('main', {
    state: () => ({
        cart: [],
        cartOpen: false,
    }),
    getters: {
        cartItemCount() {
            return this.cart.length;
        }
    },
    actions: {
        addToCart(item) {
            this.cart.push(item);
        },
        removeFromCart(index) {
            this.cart.splice(index, 1);
        },
        toggleCart() {
            this.cartOpen = !this.cartOpen;
        },
    },
});

export {pinia, useMainStore};
