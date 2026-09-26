import { mount } from "@vue/test-utils";
import PageSection from "@/Components/page/PageSection.vue";

describe("PageSection", () => {
    function mountSection(options = {}) {
        return mount(PageSection, {
            ...options,
            global: {
                stubs: {
                    Card: { template: "<article><slot /></article>" },
                    CardHeader: { template: "<header><slot /></header>" },
                    CardTitle: { template: "<h2><slot /></h2>" },
                    CardDescription: { template: "<p><slot /></p>" },
                    CardContent: { props: ["class"], template: "<main :class=\"$props.class\"><slot /></main>" },
                },
            },
        });
    }

    it("renders its title and description", () => {
        const wrapper = mountSection({ props: { title: "Usage", description: "This month" } });

        expect(wrapper.get("h2").text()).toBe("Usage");
        expect(wrapper.text()).toContain("This month");
    });

    it("renders the header actions slot", () => {
        const wrapper = mountSection({ props: { title: "Usage" }, slots: { headerActions: "<button>Export</button>" } });

        expect(wrapper.get("button").text()).toBe("Export");
    });
});
