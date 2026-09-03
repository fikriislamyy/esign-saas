<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import { ShieldCheck, RefreshCw } from "lucide-vue-next";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent } from "@/components/ui/card";

import { ref } from "vue";

const props = defineProps({
    token: {
        type: String,
        required: true,
    },

    email: {
        type: String,
        required: true,
    },
});

const form = useForm({
    otp: "",
});

const resendForm = useForm({});

const resendCooldown = ref(0);

const verify = () => {
    form.post(route("signing.otp.verify", props.token));
};

const resend = () => {
    if (resendCooldown.value > 0) {
        return;
    }

    resendForm.post(route("signing.otp.resend", props.token), {
        preserveScroll: true,

        onSuccess: () => {
            resendCooldown.value = 60;

            const interval = setInterval(() => {
                resendCooldown.value--;

                if (resendCooldown.value <= 0) {
                    clearInterval(interval);
                }
            }, 1000);
        },
    });
};
</script>

<template>
    <Head title="Verify your identity" />

    <div class="min-h-screen bg-muted/30 flex items-center justify-center p-6">
        <Card class="w-full max-w-md shadow-sm">
            <CardContent class="p-8">
                <div class="text-center">
                    <div
                        class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border bg-muted/60"
                    >
                        <ShieldCheck class="h-7 w-7" />
                    </div>

                    <h1 class="mt-6 text-2xl font-bold tracking-tight">
                        Verify your identity
                    </h1>

                    <p class="mt-2 text-sm text-muted-foreground">
                        We've sent a 6-digit verification code to
                        <span class="font-medium text-foreground">
                            {{ email }}
                        </span>
                    </p>
                </div>

                <form @submit.prevent="verify" class="mt-8 space-y-6">
                    <div class="space-y-2">
                        <Input
                            v-model="form.otp"
                            inputmode="numeric"
                            maxlength="6"
                            autocomplete="one-time-code"
                            placeholder="000000"
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
                        Verify Code
                    </Button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-sm text-muted-foreground">
                        Didn't receive the code?
                    </p>

                    <Button
                        variant="ghost"
                        class="mt-1"
                        :disabled="resendForm.processing || resendCooldown > 0"
                        @click="resend"
                    >
                        <RefreshCw class="mr-2 h-4 w-4" />

                        {{
                            resendCooldown > 0
                                ? `Resend in ${resendCooldown}s`
                                : "Resend code"
                        }}
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
