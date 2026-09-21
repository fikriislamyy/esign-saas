import { mount } from "@vue/test-utils";

vi.mock("@/Components/payment/PaymentDialog.vue", () => ({
    default: { template: "<div><slot name=\"trigger\" /></div>" },
}));

import WalletCard from "@/Components/billing/WalletCard.vue";

describe("WalletCard", () => {
    function mountCard(wallet) {
        return mount(WalletCard, {
            props: { wallet },
            global: {
                stubs: {
                    Card: { template: "<article><slot /></article>" },
                    Button: { template: "<button><slot /></button>" },
                },
            },
        });
    }

    it("formats the wallet balance as dollars", () => {
        const wrapper = mountCard({ balanceUsdCents: 1000 });

        expect(wrapper.text()).toContain("$10.00");
    });

    it("shows the unavailable message without a conversion rate", () => {
        const wrapper = mountCard({ balanceUsdCents: 0, usdToIdrRate: null });

        expect(wrapper.text()).toContain("IDR conversion rate currently unavailable.");
    });

    it("formats supplied Indonesian balance and exchange rate", () => {
        const wrapper = mountCard({ balanceUsdCents: 1000, balanceIdr: 160000, usdToIdrRate: 16000 });

        expect(wrapper.text()).toContain("Rp");
        expect(wrapper.text()).toContain("16.000");
    });
});
