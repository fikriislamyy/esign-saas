import { mount } from "@vue/test-utils";

const { post, plans } = vi.hoisted(() => ({ post: vi.fn(), plans: {
    free: { label: "Free", price_usd_cents: 0, description: "Start", limits: { documents: { limit: 3, period: "week" }, members: 3, storage_bytes: 1024 } },
    pro: { label: "Pro", price_usd_cents: 1000, description: "Grow", limits: { documents: { limit: 100, period: "month" }, members: 10, storage_bytes: 10 * 1024 ** 3 } },
    enterprise: { label: "Enterprise", price_usd_cents: 5000, description: "Scale", limits: { documents: { limit: null, period: "month" }, members: null, storage_bytes: null } },
} }));

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { plans, salesMailto: "mailto:sales@example.test" } }),
    router: { post },
}));

import PlanPickerDialog from "@/Components/plan/PlanPickerDialog.vue";

describe("PlanPickerDialog", () => {
    function mountDialog(currentPlan = "free") {
        return mount(PlanPickerDialog, {
            props: { open: true, currentPlan },
            global: {
                stubs: {
                    Dialog: { template: "<div><slot /></div>" }, DialogContent: { template: "<div><slot /></div>" }, DialogHeader: { template: "<div><slot /></div>" }, DialogTitle: { template: "<h2><slot /></h2>" }, DialogDescription: { template: "<p><slot /></p>" }, Badge: { template: "<span><slot /></span>" }, Button: { props: ["disabled"], template: "<button :disabled=\"disabled\"><slot /></button>" },
                },
            },
        });
    }

    beforeEach(() => { post.mockReset(); });

    it("renders all plans with their prices and benefits", () => {
        const wrapper = mountDialog();

        expect(wrapper.text()).toContain("Free");
        expect(wrapper.text()).toContain("$10");
        expect(wrapper.text()).toContain("$50");
        expect(wrapper.text()).toContain("3 documents per week");
        expect(wrapper.text()).toContain("Unlimited members");
        expect(wrapper.text()).toContain("10 GB storage");
    });

    it("marks the current plan as unavailable", () => {
        const wrapper = mountDialog("pro");

        expect(wrapper.text()).toContain("Current");
        expect(wrapper.get('button[disabled]').text()).toBe("Current plan");
    });

    it("emits selection for Pro", async () => {
        const wrapper = mountDialog();

        await wrapper.findAll("button").find((button) => button.text() === "Upgrade to Pro").trigger("click");

        expect(wrapper.emitted("select")).toEqual([["pro"]]);
    });

    it("only downgrades after confirmation", async () => {
        const confirm = vi.spyOn(window, "confirm").mockReturnValue(false);
        const wrapper = mountDialog("pro");

        await wrapper.findAll("button").find((button) => button.text() === "Downgrade").trigger("click");
        expect(post).not.toHaveBeenCalled();

        confirm.mockReturnValue(true);
        await wrapper.findAll("button").find((button) => button.text() === "Downgrade").trigger("click");
        expect(post).toHaveBeenCalledWith("/plan.downgrade");
    });
});
