import { mount } from "@vue/test-utils";

const { forms, post } = vi.hoisted(() => ({ forms: [], post: vi.fn() }));

vi.mock("@inertiajs/vue3", async () => {
    const { h, reactive } = await import("vue");

    return {
        Head: { template: "<div />" },
        Link: {
            props: ["href", "method", "as"],
            setup: (props, { slots }) => () => h(props.as === "button" ? "button" : "a", { href: props.href }, slots.default?.()),
        },
        usePage: () => ({ props: { auth: { user: { email: "sam@example.test" } } } }),
        useForm: (data) => {
            const form = reactive({ ...data, errors: {}, processing: false, post });
            forms.push(form);
            return form;
        },
    };
});

import VerifyEmail from "@/Pages/Auth/VerifyEmail.vue";

describe("VerifyEmail", () => {
    let wrapper;

    function mountPage(props = {}) {
        return mount(VerifyEmail, {
            props,
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

    it("shows the signed-in user's email", () => {
        wrapper = mountPage();

        expect(wrapper.text()).toContain("sam@example.test");
    });

    it("enables verification only after six digits are entered", async () => {
        wrapper = mountPage();
        const verify = wrapper.findAll("button").find((button) => button.text() === "Verify Code");
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

        expect(post).toHaveBeenCalledWith("/verification.verify");
    });

    it("resends the code and starts a sixty second cooldown", async () => {
        vi.useFakeTimers();
        wrapper = mountPage();
        post.mockImplementationOnce((_url, options) => options.onSuccess());

        await wrapper.findAll("button").find((button) => button.text() === "Resend code").trigger("click");
        await wrapper.vm.$nextTick();

        expect(post).toHaveBeenCalledWith("/verification.send", expect.objectContaining({ preserveScroll: true, onSuccess: expect.any(Function) }));
        expect(wrapper.text()).toContain("Resend in 60s");
    });

    it("shows the verification code sent banner for the matching status", () => {
        wrapper = mountPage({ status: "verification-code-sent" });

        expect(wrapper.text()).toContain("Verification code sent");
    });
});
