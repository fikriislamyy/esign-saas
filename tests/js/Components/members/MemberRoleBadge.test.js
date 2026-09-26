import { mount } from "@vue/test-utils";
import MemberRoleBadge from "@/Components/members/MemberRoleBadge.vue";

describe("MemberRoleBadge", () => {
    function mountBadge(role) {
        return mount(MemberRoleBadge, {
            props: { role },
            global: { stubs: { Badge: { props: ["variant"], template: "<span :data-variant=\"variant\"><slot /></span>" } } },
        });
    }

    it("renders the owner label and default variant", () => {
        const wrapper = mountBadge("owner");

        expect(wrapper.text()).toContain("Owner");
        expect(wrapper.get("span").attributes("data-variant")).toBe("default");
    });

    it("renders the admin label and secondary variant", () => {
        const wrapper = mountBadge("admin");

        expect(wrapper.text()).toContain("Admin");
        expect(wrapper.get("span").attributes("data-variant")).toBe("secondary");
    });

    it("renders the member label and outline variant", () => {
        const wrapper = mountBadge("member");

        expect(wrapper.text()).toContain("Member");
        expect(wrapper.get("span").attributes("data-variant")).toBe("outline");
    });

    it("falls back to the member presentation for an unknown role", () => {
        const wrapper = mountBadge("viewer");

        expect(wrapper.text()).toContain("Member");
        expect(wrapper.get("span").attributes("data-variant")).toBe("outline");
    });
});
