import { mount } from "@vue/test-utils";
import { nextTick } from "vue";

const { forms, post } = vi.hoisted(() => ({ forms: [], post: vi.fn() }));

vi.mock("@inertiajs/vue3", async () => {
    const { h, reactive } = await import("vue");

    return {
        Head: { template: "<div />" },
        Link: {
            props: ["href"],
            setup: (props, { slots }) => () => h("a", { href: props.href }, slots.default?.()),
        },
        useForm: (data) => {
            const form = reactive({ ...data, errors: {}, processing: false, post });
            forms.push(form);
            return form;
        },
    };
});

import LoginOtp from "@/Pages/Auth/LoginOtp.vue";

describe("LoginOtp", () => {
    let wrapper;

    function mountPage(props = {}) {
        return mount(LoginOtp, {
            props: { email: "sam@example.test", resendAfter: 0, ...props },
            global: {
                stubs: {
                    AuthLayout: { template: "<main><slot /></main>" },
                    Button: { props: ["disabled", "type"], template: "<button :type='type' :disabled='disabled'><slot /></button>" },
                    Input: {
                        props: ["modelValue"],
                        emits: ["update:modelValue"],
                        template: "<input :value='modelValue' @input='$emit(\"update:modelValue\", $event.target.value)' />",
                    },
                    Card: { template: "<section><slot /></section>" },
                    CardContent: { template: "<div><slot /></div>" },
                    CardDescription: { template: "<p><slot /></p>" },
                    CardHeader: { template: "<header><slot /></header>" },
                    CardTitle: { template: "<h1><slot /></h1>" },
                },
            },
        });
    }

    beforeEach(() => {
        forms.length = 0;
        post.mockReset();
    });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = undefined;
        vi.useRealTimers();
    });

    it("shows the email address", () => {
        wrapper = mountPage();

        expect(wrapper.text()).toContain("sam@example.test");
    });

    it("starts the resend countdown from the provided delay", async () => {
        vi.useFakeTimers();
        wrapper = mountPage({ resendAfter: 12 });
        await nextTick();

        expect(wrapper.props("resendAfter")).toBe(12);
        const resend = wrapper.findAll("button").find((button) => button.text().startsWith("Resend in"));

        expect(resend.text()).toBe("Resend in 12s");
        expect(resend.attributes("disabled")).toBeDefined();
    });

    it("enables resend when the countdown finishes", async () => {
        vi.useFakeTimers();
        wrapper = mountPage({ resendAfter: 2 });

        await vi.advanceTimersByTimeAsync(2000);
        await wrapper.vm.$nextTick();

        const resend = wrapper.findAll("button").find((button) => button.text() === "Resend code");
        expect(resend.attributes("disabled")).toBeUndefined();
    });

    it("enables verification only after six digits are entered", async () => {
        wrapper = mountPage();
        const verify = wrapper.findAll("button").find((button) => button.text() === "Verify and sign in");
        const input = wrapper.get("input");

        expect(verify.attributes("disabled")).toBeDefined();
        await input.setValue("12345");
        expect(verify.attributes("disabled")).toBeDefined();
        await input.setValue("123456");
        expect(verify.attributes("disabled")).toBeUndefined();
    });

    it("posts the verification form to the verify route", async () => {
        wrapper = mountPage();
        await wrapper.get("form").trigger("submit");

        expect(post).toHaveBeenCalledWith("/login.otp.verify");
    });

    it("resends the code and starts a new sixty second cooldown", async () => {
        vi.useFakeTimers();
        wrapper = mountPage();
        post.mockImplementationOnce((_url, options) => options.onSuccess());

        await wrapper.findAll("button").find((button) => button.text() === "Resend code").trigger("click");
        await wrapper.vm.$nextTick();

        expect(post).toHaveBeenCalledWith("/login.otp.resend", expect.objectContaining({ preserveScroll: true, onSuccess: expect.any(Function) }));
        expect(wrapper.text()).toContain("Resend in 60s");
    });

    it("shows the new code sent banner for the matching status", () => {
        wrapper = mountPage({ status: "login-code-sent" });

        expect(wrapper.text()).toContain("New code sent");
    });

    it("renders resend validation errors", async () => {
        wrapper = mountPage();
        forms[1].errors.otp = "Please wait before requesting another code.";
        await nextTick();

        expect(wrapper.text()).toContain("Please wait before requesting another code.");
    });

    it("links to login to use a different account", () => {
        wrapper = mountPage();

        expect(wrapper.get('a[href="/login"]').text()).toContain("Use a different account");
    });
});
