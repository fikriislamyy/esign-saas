import { mount } from "@vue/test-utils";

const { get } = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock("@inertiajs/vue3", () => ({ router: { get } }));

import DashboardFilter from "@/Components/dashboard/DashboardFilter.vue";

describe("DashboardFilter", () => {
    beforeEach(() => {
        get.mockReset();
        globalThis.route.mockClear();
    });

    it("requests the selected predefined range", async () => {
        const wrapper = mount(DashboardFilter, {
            global: {
                stubs: {
                    FadeIn: { template: "<div><slot /></div>" },
                    Select: { emits: ["update:modelValue"], template: "<button @click=\"$emit('update:modelValue', 'month')\"><slot /></button>" },
                    SelectTrigger: true, SelectValue: true, SelectContent: true, SelectItem: true,
                    Popover: { template: "<div><slot /></div>" }, PopoverContent: true, RangeCalendar: true, Button: true,
                },
            },
        });

        await wrapper.get("button").trigger("click");

        expect(get).toHaveBeenCalledWith("/dashboard", { range: "month" }, expect.objectContaining({ preserveState: true, replace: true }));
    });

    it("opens custom range selection without making a request", async () => {
        const wrapper = mount(DashboardFilter, {
            global: {
                stubs: {
                    FadeIn: { template: "<div><slot /></div>" },
                    Select: { emits: ["update:modelValue"], template: "<button @click=\"$emit('update:modelValue', 'custom')\"><slot /></button>" },
                    SelectTrigger: true, SelectValue: true, SelectContent: true, SelectItem: true,
                    Popover: { props: ["open"], template: "<div :data-open=\"open\"><slot /></div>" }, PopoverContent: true, RangeCalendar: true, Button: true,
                },
            },
        });

        await wrapper.get("button").trigger("click");

        expect(get).not.toHaveBeenCalled();
        expect(wrapper.html()).toContain('data-open="true"');
    });
});
