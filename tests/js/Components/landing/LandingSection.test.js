import { mount } from "@vue/test-utils";
import LandingSection from "@/Components/landing/LandingSection.vue";

describe("LandingSection", () => {
    function mountSection(props = {}, slots = {}) {
        return mount(LandingSection, {
            props: { title: "A useful title", ...props },
            slots,
            global: { stubs: { FadeIn: { template: "<div><slot /></div>" } } },
        });
    }

    it("renders its title in an h2", () => {
        const wrapper = mountSection();

        expect(wrapper.get("h2").text()).toBe("A useful title");
    });

    it("renders an eyebrow only when one is supplied", () => {
        const withoutEyebrow = mountSection();
        const withEyebrow = mountSection({ eyebrow: "Overview" });

        expect(withoutEyebrow.text()).not.toContain("Overview");
        expect(withEyebrow.text()).toContain("Overview");
    });

    it("renders a subtitle only when one is supplied", () => {
        const withoutSubtitle = mountSection();
        const withSubtitle = mountSection({ subtitle: "Helpful context" });

        expect(withoutSubtitle.text()).not.toContain("Helpful context");
        expect(withSubtitle.text()).toContain("Helpful context");
    });

    it("applies the muted background class when muted", () => {
        const wrapper = mountSection({ muted: true });

        expect(wrapper.get("section").classes()).toContain("bg-muted/40");
    });

    it("renders its default slot", () => {
        const wrapper = mountSection({}, { default: "<p>Slot content</p>" });

        expect(wrapper.text()).toContain("Slot content");
    });

    it("sets its id on the section", () => {
        const wrapper = mountSection({ id: "overview" });

        expect(wrapper.get("section").attributes("id")).toBe("overview");
    });
});
