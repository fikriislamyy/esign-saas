import { mount } from "@vue/test-utils";
import WhatIsDigitalSignature from "@/Components/landing/WhatIsDigitalSignature.vue";

describe("WhatIsDigitalSignature", () => {
    it("renders both explanatory headings", () => {
        const wrapper = mount(WhatIsDigitalSignature, {
            global: { stubs: { LandingSection: { template: "<section><slot /></section>" } } },
        });

        expect(wrapper.findAll("h3").map((heading) => heading.text().trim())).toEqual([
            "Digital signature vs. electronic signature",
            "Is a digital signature legally binding?",
        ]);
    });

    it("sets the digital signature section id", () => {
        const wrapper = mount(WhatIsDigitalSignature, {
            global: { stubs: { LandingSection: { props: ["id"], template: "<section :id=\"id\"><slot /></section>" } } },
        });

        expect(wrapper.get("section").attributes("id")).toBe("what-is-a-digital-signature");
    });
});
