import { mount } from "@vue/test-utils";
import { defineComponent, h, markRaw } from "vue";
import StatCard from "@/Components/common/StatCard.vue";

describe("StatCard", () => {
    it("renders its label and value", () => {
        const wrapper = mount(StatCard, {
            props: { title: "Documents", value: 42 },
            global: { stubs: { Card: { template: "<article><slot /></article>" }, CardContent: { template: "<div><slot /></div>" } } },
        });

        expect(wrapper.text()).toContain("Documents");
        expect(wrapper.text()).toContain("42");
    });

    it("renders the supplied icon", () => {
        const icon = markRaw(defineComponent({ setup: () => () => h("svg") }));
        const wrapper = mount(StatCard, {
            props: { title: "Documents", value: 42, icon },
            global: { stubs: { Card: { template: "<article><slot /></article>" }, CardContent: { template: "<div><slot /></div>" } } },
        });

        expect(wrapper.find("svg").exists()).toBe(true);
    });
});
