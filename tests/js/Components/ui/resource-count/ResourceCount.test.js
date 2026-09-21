import { mount } from "@vue/test-utils";
import ResourceCount from "@/Components/ui/resource-count/ResourceCount.vue";

describe("ResourceCount", () => {
    it("renders the count with its plural label", () => {
        const wrapper = mount(ResourceCount, { props: { count: 2, singular: "document" } });

        expect(wrapper.findAll("span").map((span) => span.text())).toEqual(["2", "documents"]);
    });

    it("uses the singular label for one item", () => {
        const wrapper = mount(ResourceCount, { props: { count: 1, singular: "document" } });

        expect(wrapper.findAll("span").map((span) => span.text())).toEqual(["1", "document"]);
    });

    it("uses an explicitly supplied plural label", () => {
        const wrapper = mount(ResourceCount, { props: { count: 2, singular: "person", plural: "people" } });

        expect(wrapper.findAll("span").map((span) => span.text())).toEqual(["2", "people"]);
    });

    it("can hide the label", () => {
        const wrapper = mount(ResourceCount, { props: { count: 2, singular: "document", showLabel: false } });

        expect(wrapper.text()).toBe("2");
    });
});
