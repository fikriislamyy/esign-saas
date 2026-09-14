<script setup>
import { ref } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

import AuthLayout from "@/Layouts/AuthLayout.vue";

import { Lock, Eye, EyeOff, Loader2, ShieldCheck } from "lucide-vue-next";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";

const props = defineProps({
    email: {
        type: String,
        required: true,
    },

    token: {
        type: String,
        required: true,
    },
});

const showPassword = ref(false);
const showConfirmation = ref(false);

const form = useForm({
    token: props.token,
    email: props.email,
    password: "",
    password_confirmation: "",
});

const submit = () => {
    form.post(route("password.store"), {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
};
</script>

<template>
    <Head title="Reset Password" />

    <AuthLayout>
        <Card
            class="w-full max-w-md rounded-2xl border shadow-xl bg-background/95 backdrop-blur"
        >
            <CardHeader class="space-y-4 text-center">
                <div
                    class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary/10"
                >
                    <ShieldCheck class="h-8 w-8 text-primary" />
                </div>

                <div>
                    <CardTitle class="text-3xl font-bold">
                        Reset Password
                    </CardTitle>

                    <CardDescription class="mt-2 text-base">
                        Create a new password for your account.
                    </CardDescription>
                </div>
            </CardHeader>

            <CardContent class="space-y-6">
                <!-- Password -->

                <div class="space-y-2">
                    <Label for="password"> New Password </Label>

                    <div class="relative">
                        <Lock
                            class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />

                        <Input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="new-password"
                            class="pl-10 pr-10"
                            autofocus
                        />

                        <button
                            type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            @click="showPassword = !showPassword"
                        >
                            <Eye v-if="!showPassword" class="h-4 w-4" />

                            <EyeOff v-else class="h-4 w-4" />
                        </button>
                    </div>

                    <p
                        v-if="form.errors.password"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.password }}
                    </p>
                </div>

                <!-- Confirm -->

                <div class="space-y-2">
                    <Label for="password_confirmation">
                        Confirm Password
                    </Label>

                    <div class="relative">
                        <Lock
                            class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />

                        <Input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            :type="showConfirmation ? 'text' : 'password'"
                            autocomplete="new-password"
                            class="pl-10 pr-10"
                        />

                        <button
                            type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            @click="showConfirmation = !showConfirmation"
                        >
                            <Eye v-if="!showConfirmation" class="h-4 w-4" />

                            <EyeOff v-else class="h-4 w-4" />
                        </button>
                    </div>

                    <p
                        v-if="form.errors.password_confirmation"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.password_confirmation }}
                    </p>
                </div>

                <Button
                    class="h-11 w-full text-base font-semibold"
                    :disabled="form.processing"
                    @click="submit"
                >
                    <Loader2
                        v-if="form.processing"
                        class="mr-2 h-4 w-4 animate-spin"
                    />

                    {{ form.processing ? "Updating..." : "Reset Password" }}
                </Button>

                <div class="relative py-2">
                    <div class="absolute inset-0 flex items-center">
                        <span class="w-full border-t"></span>
                    </div>

                    <div class="relative flex justify-center">
                        <span
                            class="bg-card px-3 text-xs text-muted-foreground"
                        >
                            Back to sign in
                        </span>
                    </div>
                </div>

                <div class="text-center">
                    <Link
                        :href="route('login')"
                        class="font-semibold text-primary hover:underline"
                    >
                        Return to Login
                    </Link>
                </div>
            </CardContent>
        </Card>
    </AuthLayout>
</template>
