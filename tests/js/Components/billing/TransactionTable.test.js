import { mount } from "@vue/test-utils";
import TransactionTable from "@/Components/billing/TransactionTable.vue";

describe("TransactionTable", () => {
    function mountTable(transaction) {
        return mount(TransactionTable, {
            props: { transactions: { data: [transaction], links: [] } },
            global: { stubs: { Button: { template: "<button><slot /></button>" } } },
        });
    }

    it("formats a credit amount in dollars with a plus sign", () => {
        const wrapper = mountTable({ id: 1, type: "topup", amount_usd_cents: 1000, balance_after_usd_cents: 1000 });

        expect(wrapper.text()).toContain("+$10.00");
        expect(wrapper.text()).toContain("$10.00");
    });

    it("formats a debit amount with a minus sign and muted colour", () => {
        const wrapper = mountTable({ id: 1, type: "signature", amount_usd_cents: -250, balance_after_usd_cents: 750 });

        expect(wrapper.text()).toContain("-$2.50");
        expect(wrapper.findAll(".text-muted-foreground").some((node) => node.text().includes("-$2.50"))).toBe(true);
    });
});
