import { mount } from "@vue/test-utils";
import { h } from "vue";

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { salesMailto: "mailto:sales@example.test?subject=Enterprise" } }),
    Link: {
        props: ["href"],
        setup: (props, { slots }) => () => h("a", { href: props.href, "data-inertia": "" }, slots.default?.()),
    },
}));

import Pricing from "@/Components/landing/Pricing.vue";

describe("Pricing", () => {
    function mountPricing() {
        const ButtonStub = {
            props: ["as", "href"],
            setup: (props, { slots }) => () => h(props.as || "button", { href: props.href }, slots.default?.()),
        };

        return mount(Pricing, {
            global: {
                stubs: {
                    FadeIn: { template: "<div><slot /></div>" },
                    LandingSection: { template: "<section><slot /></section>" },
                    Card: { template: "<article><slot /></article>" },
                    CardHeader: { template: "<header><slot /></header>" },
                    CardContent: { template: "<div><slot /></div>" },
                    CardTitle: { template: "<h3><slot /></h3>" },
                    CardDescription: { template: "<p><slot /></p>" },
                    Badge: { template: "<span><slot /></span>" },
                    Button: ButtonStub,
                },
            },
        });
    }

    it("renders the three plan names", () => {
        const wrapper = mountPricing();

        expect(wrapper.findAll("h3").map((heading) => heading.text())).toEqual(["Starter", "Pro", "Enterprise"]);
    });

    it("renders Enterprise as a plain mailto link", () => {
        const wrapper = mountPricing();

        const link = wrapper.get('a[href^="mailto:"]');

        expect(link.attributes("data-inertia")).toBeUndefined();
        expect(link.text()).toBe("Contact sales");
    });

    it("renders Starter and Pro through Inertia links", () => {
        const wrapper = mountPricing();

        expect(wrapper.get('a[href="/register"]').attributes("data-inertia")).toBeDefined();
        expect(wrapper.get('a[href="/plan"]').attributes("data-inertia")).toBeDefined();
    });

    it("marks only Pro as most popular", () => {
        const wrapper = mountPricing();

        expect(wrapper.findAll("span").filter((element) => element.text() === "Most popular")).toHaveLength(1);
    });

    it("lists four benefits for every plan", () => {
        const wrapper = mountPricing();

        expect(wrapper.findAll("ul").map((list) => list.findAll("li").length)).toEqual([4, 4, 4]);
    });
});
