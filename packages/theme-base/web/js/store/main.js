import {defineStore, createPinia} from 'pinia';

const pinia = createPinia();

const useMainStore = defineStore('main', {
    state: () => ({
        cart: [],
        cartOpen: false,
        searchOpen: false,
        searchLink: null
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
        toggleSearch() {
            this.searchOpen = !this.searchOpen;
        },
        setSearchLink(link) {
            this.searchLink = link;
        }
    },
});

export {pinia, useMainStore};
