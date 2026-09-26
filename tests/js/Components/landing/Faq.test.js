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

        expect(wrapper.findAll("summary").map((summary) => summary.text())).toEqual([
            "Are electronic signatures legally binding?",
            "Do signers need an account?",
            "What file types can I upload?",
            "How does the OTP verification work?",
            "How does pricing work?",
            "Can my whole team use one account?",
            "Can a signed PDF be changed afterwards?",
            "Where are my documents stored?",
        ]);
    });

    it("keeps answers in the DOM", () => {
        const wrapper = mountFaq();

        expect(wrapper.findAll("details p")).toHaveLength(8);
        expect(wrapper.text()).toContain("Signers receive a link by email");
    });
});
