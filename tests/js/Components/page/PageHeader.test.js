import { mount } from "@vue/test-utils";
import PageHeader from "@/Components/page/PageHeader.vue";

describe("PageHeader", () => {
    it("renders its title and description", () => {
        const wrapper = mount(PageHeader, { props: { title: "Documents", description: "Manage your files" } });

        expect(wrapper.get("h1").text()).toBe("Documents");
        expect(wrapper.text()).toContain("Manage your files");
    });

    it("renders the actions slot", () => {
        const wrapper = mount(PageHeader, { props: { title: "Documents" }, slots: { actions: "<button>Create</button>" } });

        expect(wrapper.get("button").text()).toBe("Create");
    });
});
