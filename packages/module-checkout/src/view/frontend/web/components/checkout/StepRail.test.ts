import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import StepRail from "./StepRail.vue";

const STEPS = [
    { key: "identification", label: "Identification", index: 0, done: true, active: false, reachable: true },
    { key: "shipping", label: "Shipping", index: 1, done: true, active: false, reachable: true },
    { key: "payment", label: "Payment", index: 2, done: false, active: true, reachable: false },
    { key: "review", label: "Review", index: 3, done: false, active: false, reachable: false },
];

const render = (steps = STEPS) => mount(StepRail, { props: { steps } });

const states = (wrapper: ReturnType<typeof render>): string[] =>
    wrapper.findAll(".step-rail__item").map((item) => item.attributes("data-state") ?? "");

describe("StepRail", () => {
    it("tells done, current and pending apart", () => {
        expect(states(render())).toEqual(["done", "done", "active", "pending"]);
    });

    it("keeps a step marked done after the shopper has moved past it", () => {
        const wrapper = render();
        const shipping = wrapper.findAll(".step-rail__item")[1];

        expect(shipping.attributes("data-state")).toBe("done");
        expect(shipping.attributes("data-state")).not.toBe(
            wrapper.findAll(".step-rail__item")[3].attributes("data-state"),
        );
    });

    it("marks a done step with a check and a pending one with its number", () => {
        const marks = render().findAll(".step-rail__mark");

        expect(marks[0].find("svg").exists()).toBe(true);
        expect(marks[3].find("svg").exists()).toBe(false);
        expect(marks[3].text()).toBe("4");
    });

    it("points assistive tech at the current step", () => {
        const wrapper = render();
        const current = wrapper.findAll('[aria-current="step"]');

        expect(current).toHaveLength(1);
        expect(current[0].text()).toContain("Payment");
    });

    it("spells out done and current for assistive tech", () => {
        const wrapper = render();
        const items = wrapper.findAll(".step-rail__item");

        expect(items[0].find(".sr-only").text()).toBe("completed");
        expect(items[2].find(".sr-only").text()).toBe("current step");
        expect(items[3].find(".sr-only").exists()).toBe(false);
    });

    it("lets the shopper back into a step they finished", async () => {
        const wrapper = render();

        await wrapper.findAll(".step-rail__button")[1].trigger("click");

        expect(wrapper.emitted("go")?.[0][0]).toMatchObject({ key: "shipping" });
    });

    it("locks the steps that are not reachable yet", () => {
        const buttons = render().findAll(".step-rail__button");

        expect(buttons[1].attributes("disabled")).toBeUndefined();
        expect(buttons[3].attributes("disabled")).toBeDefined();
    });
});
