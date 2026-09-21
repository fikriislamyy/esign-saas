import { mount } from "@vue/test-utils";
import Faq from "@/Components/landing/Faq.vue";

describe("Faq", () => {
    function mountFaq() {
        return mount(Faq, {
            global: { stubs: { LandingSection: { template: "<section><slot /></section>" } } },
        });
    }

    it("renders eight questions", () => {
        const wrapper = mountFaq();

        expect(wrapper.findAll("details")).toHaveLength(8);
    });

    it("renders each question in a summary", () => {
        const wrapper = mountFaq();

        expect(wrapper.findAll("summary").map((summary) => summary.text())).toEqual(expect.arrayContaining([
            "Are electronic signatures legally binding?",
            "Do signers need an account?",
            "Where are my documents stored?",
        ]));
    });

    it("keeps answers in the DOM", () => {
        const wrapper = mountFaq();

        expect(wrapper.findAll("details p")).toHaveLength(8);
        expect(wrapper.text()).toContain("Signers receive a link by email");
    });
});
