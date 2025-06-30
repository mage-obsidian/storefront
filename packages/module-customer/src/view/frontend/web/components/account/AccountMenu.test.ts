import { describe, it, expect, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import AccountMenu from "./AccountMenu.vue";
import { __setSection, __reset } from "../../../../../Test/Js/stubs/customerData.ts";

const URLS = {
    accountUrl: "/customer/account",
    loginUrl: "/customer/account/login",
    logoutUrl: "/customer/account/logout",
    ordersUrl: "/sales/order/history",
    addressesUrl: "/customer/address",
};

describe("AccountMenu.vue", () => {
    beforeEach(() => __reset());

    it("shows a Sign In link for guests", () => {
        const wrapper = mount(AccountMenu, { props: { ...URLS, signInLabel: "Sign In" } });
        const link = wrapper.get("a");
        expect(link.text()).toBe("Sign In");
        expect(link.attributes("href")).toBe("/customer/account/login");
        expect(wrapper.find("button").exists()).toBe(false);
    });

    it("shows a disclosure with the first name when logged in", async () => {
        __setSection("customer", { firstname: "Ada" });
        const wrapper = mount(AccountMenu, { props: { ...URLS } });
        await wrapper.vm.$nextTick();

        const trigger = wrapper.get("button");
        expect(trigger.text()).toContain("Ada");
        expect(trigger.attributes("aria-expanded")).toBe("false");
        expect(trigger.attributes("aria-label")).toContain("Ada");
        // Collapsed: the panel is not rendered.
        expect(wrapper.find("ul").exists()).toBe(false);
    });

    it("opens the menu with account links and toggles aria-expanded", async () => {
        __setSection("customer", { firstname: "Ada" });
        const wrapper = mount(AccountMenu, {
            props: { ...URLS, myOrdersLabel: "My Orders", signOutLabel: "Sign Out" },
            attachTo: document.body,
        });
        await wrapper.vm.$nextTick();

        await wrapper.get("button").trigger("click");
        expect(wrapper.get("button").attributes("aria-expanded")).toBe("true");

        const links = wrapper.findAll("ul a");
        const labels = links.map((l) => l.text());
        expect(labels).toContain("My Orders");
        expect(labels).toContain("Sign Out");
        const logout = links.find((l) => l.text() === "Sign Out");
        expect(logout?.attributes("href")).toBe("/customer/account/logout");

        wrapper.unmount();
    });

    it("closes on Escape", async () => {
        __setSection("customer", { firstname: "Ada" });
        const wrapper = mount(AccountMenu, { props: { ...URLS }, attachTo: document.body });
        await wrapper.vm.$nextTick();

        await wrapper.get("button").trigger("click");
        expect(wrapper.find("ul").exists()).toBe(true);

        await wrapper.get("div").trigger("keydown.escape");
        expect(wrapper.find("ul").exists()).toBe(false);

        wrapper.unmount();
    });
});
