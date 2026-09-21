<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import AuthLayout from "@/Layouts/AuthLayout.vue";
import { ShieldCheck, MailCheck, RefreshCcw, ArrowLeft } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

const props = defineProps({
    email: String,
    status: String,
    resendAfter: Number,
});

const form = useForm({ otp: "" });
const resendForm = useForm({});
const resendCooldown = ref(0);
let timer = null;

const startCooldown = (seconds) => {
    clearInterval(timer);
    resendCooldown.value = seconds;

    if (seconds <= 0) {
        return;
    }

    timer = setInterval(() => {
        resendCooldown.value--;

        if (resendCooldown.value <= 0) {
            clearInterval(timer);
        }
    }, 1000);
};

onMounted(() => startCooldown(props.resendAfter ?? 0));
onBeforeUnmount(() => clearInterval(timer));

const verify = () => form.post(route("login.otp.verify"));

const resend = () => {
    if (resendCooldown.value > 0) {
        return;
    }

    resendForm.post(route("login.otp.resend"), {
        preserveScroll: true,
        onSuccess: () => startCooldown(60),
    });
};

const codeSent = computed(() => props.status === "login-code-sent");
</script>

<template>
    <Head title="Check your email" />

    <AuthLayout>
        <Card class="w-full max-w-md rounded-2xl border shadow-xl bg-background/95">
            <CardHeader class="items-center text-center space-y-5">
                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-primary/10">
                    <ShieldCheck class="h-10 w-10 text-accent-ink" />
                </div>

                <div>
                    <CardTitle class="text-3xl font-bold">
                        Check your email
                    </CardTitle>

                    <CardDescription class="mt-2 text-base">
                        Enter the 6-digit code we sent to <span class="font-semibold text-foreground">{{ email }}</span>. It expires in 10 minutes.
                    </CardDescription>
                </div>
            </CardHeader>

            <CardContent class="space-y-6">
                <div
                    v-if="codeSent"
                    class="rounded-xl border border-emerald-200 bg-pulse-green/15 p-4"
                >
                    <div class="flex gap-3">
                        <MailCheck class="mt-0.5 h-5 w-5 text-pulse-green" />

                        <div>
                            <p class="font-medium text-pulse-green">
                                New code sent
                            </p>

                            <p class="mt-1 text-sm text-pulse-green">
                                Check your inbox for the latest code.
                            </p>
                        </div>
                    </div>
                </div>

                <form @submit.prevent="verify" class="space-y-4">
                    <div class="space-y-2">
                        <Input
                            v-model="form.otp"
                            inputmode="numeric"
                            maxlength="6"
                            autocomplete="one-time-code"
                            placeholder="000000"
                            autofocus
                            class="text-center text-2xl tracking-[0.5em]"
                        />

                        <p
                            v-if="form.errors.otp"
                            class="text-sm text-destructive text-center"
                        >
                            {{ form.errors.otp }}
                        </p>
                    </div>

                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="form.processing || form.otp.length !== 6"
                    >
                        Verify and sign in
                    </Button>
                </form>

                <div class="text-center">
                    <p class="text-sm text-muted-foreground">
                        Didn't receive the code?
                    </p>

                    <Button
                        variant="ghost"
                        class="mt-1"
                        :disabled="resendForm.processing || resendCooldown > 0"
                        @click="resend"
                    >
                        <RefreshCcw class="mr-2 h-4 w-4" />

                        {{
                            resendCooldown > 0
                                ? `Resend in ${resendCooldown}s`
                                : "Resend code"
                        }}
                    </Button>

                    <p
                        v-if="resendForm.errors.otp"
                        class="mt-2 text-sm text-destructive"
                    >
                        {{ resendForm.errors.otp }}
                    </p>
                </div>

                <Link
                    :href="route('login')"
                    class="flex w-full items-center justify-center rounded-lg border py-2.5 text-sm font-medium transition hover:bg-muted"
                >
                    <ArrowLeft class="mr-2 h-4 w-4" />

                    Use a different account
                </Link>
            </CardContent>
        </Card>
    </AuthLayout>
</template>
