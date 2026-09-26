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
    let wrapper;

    function mountDialog(currentPlan = "free") {
        wrapper = mount(PlanPickerDialog, {
            props: { open: true, currentPlan },
            attachTo: document.body,
            global: {
                stubs: {
                    Dialog: { template: "<div><slot /></div>" }, DialogContent: { template: "<div><slot /></div>" }, DialogHeader: { template: "<div><slot /></div>" }, DialogTitle: { template: "<h2><slot /></h2>" }, DialogDescription: { template: "<p><slot /></p>" }, Badge: { template: "<span data-badge><slot /></span>" }, Button: { props: ["disabled"], template: "<button :disabled=\"disabled\"><slot /></button>" },
                },
            },
        });
        return wrapper;
    }

    beforeEach(() => { post.mockReset(); });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = undefined;
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it("renders three plan cards", () => {
        const wrapper = mountDialog();

        expect(wrapper.findAll(".grid > div")).toHaveLength(3);
        expect(wrapper.text()).toContain("Free");
        expect(wrapper.text()).toContain("Pro");
        expect(wrapper.text()).toContain("Enterprise");
    });

    it("formats the three prices in dollars", () => {
        const wrapper = mountDialog();

        expect(wrapper.findAll(".grid > div .text-3xl").map((price) => price.text())).toEqual(["$0", "$10", "$50"]);
    });

    it("shows per month only on paid plans", () => {
        const wrapper = mountDialog();
        const cards = wrapper.findAll(".grid > div");

        expect(cards[0].text()).not.toContain("/month");
        expect(cards[1].text()).toContain("/month");
        expect(cards[2].text()).toContain("/month");
    });

    it("shows the configured document, member, and storage benefits", () => {
        const wrapper = mountDialog();

        expect(wrapper.text()).toContain("3 documents per week");
        expect(wrapper.text()).toContain("Unlimited members");
        expect(wrapper.text()).toContain("10 GB storage");
    });

    it("marks the current plan with a badge", () => {
        const wrapper = mountDialog("pro");
        const cards = wrapper.findAll(".grid > div");

        expect(wrapper.findAll("[data-badge]")).toHaveLength(1);
        expect(cards[1].get("[data-badge]").text()).toBe("Current");
    });

    it("disables the current plan button", () => {
        const wrapper = mountDialog("pro");

        expect(wrapper.get('button[disabled]').text()).toBe("Current plan");
    });

    it("emits selection for Pro", async () => {
        const wrapper = mountDialog();

        await wrapper.findAll("button").find((button) => button.text() === "Upgrade to Pro").trigger("click");

        expect(wrapper.emitted("select")).toEqual([["pro"]]);
    });

    it("does not downgrade when confirmation is declined", async () => {
        vi.spyOn(window, "confirm").mockReturnValue(false);
        const wrapper = mountDialog("pro");

        await wrapper.findAll("button").find((button) => button.text() === "Downgrade").trigger("click");

        expect(post).not.toHaveBeenCalled();
    });

    it("posts a downgrade when confirmation is accepted", async () => {
        vi.spyOn(window, "confirm").mockReturnValue(true);
        const wrapper = mountDialog("pro");

        await wrapper.findAll("button").find((button) => button.text() === "Downgrade").trigger("click");

        expect(post).toHaveBeenCalledWith("/plan.downgrade");
    });

    it("opens the sales email for Enterprise", async () => {
        const location = { href: "" };
        vi.stubGlobal("location", location);
        const wrapper = mountDialog();

        await wrapper.findAll("button").find((button) => button.text() === "Contact sales").trigger("click");

        expect(location.href).toBe("mailto:sales@example.test");
    });
});
