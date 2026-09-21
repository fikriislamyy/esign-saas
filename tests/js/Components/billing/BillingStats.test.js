import { mount } from "@vue/test-utils";
import BillingStats from "@/Components/billing/BillingStats.vue";

describe("BillingStats", () => {
    it("formats monthly and total spending in dollars", () => {
        const wrapper = mount(BillingStats, {
            props: { stats: { monthlySpentUsdCents: 1000, totalSpentUsdCents: 2500, monthlySignatureCount: 2, totalSignatureCount: 5 } },
            global: { stubs: { Card: { template: "<article><slot /></article>" } } },
        });

        expect(wrapper.text()).toContain("$10.00");
        expect(wrapper.text()).toContain("$25.00");
        expect(wrapper.text()).toContain("2");
        expect(wrapper.text()).toContain("5 signatures all time");
    });
});
