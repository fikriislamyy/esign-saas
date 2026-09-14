<script setup>
import { ref } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

import AuthLayout from "@/Layouts/AuthLayout.vue";

import { Mail, Loader2, ArrowLeft, KeyRound } from "lucide-vue-next";

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
    status: String,
});

const form = useForm({
    email: "",
});

const submit = () => {
    form.post(route("password.email"));
};
</script>

<template>
    <Head title="Forgot Password" />

    <AuthLayout>
        <Card
            class="w-full max-w-md rounded-2xl border shadow-xl bg-background/95 backdrop-blur"
        >
            <CardHeader class="space-y-4 text-center">
                <div
                    class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary/10"
                >
                    <KeyRound class="h-8 w-8 text-primary" />
                </div>

                <div>
                    <CardTitle class="text-3xl font-bold">
                        Forgot Password?
                    </CardTitle>

                    <CardDescription class="mt-2 text-base">
                        Enter your email address and we'll send you a secure
                        link to reset your password.
                    </CardDescription>
                </div>
            </CardHeader>

            <CardContent class="space-y-6">
                <div
                    v-if="status"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700"
                >
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="space-y-2">
                        <Label for="email"> Email Address </Label>

                        <div class="relative">
                            <Mail
                                class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />

                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                autocomplete="username"
                                autofocus
                                placeholder="you@example.com"
                                class="pl-10"
                            />
                        </div>

                        <p
                            v-if="form.errors.email"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <Button
                        class="h-11 w-full text-base font-semibold"
                        :disabled="form.processing"
                    >
                        <Loader2
                            v-if="form.processing"
                            class="mr-2 h-4 w-4 animate-spin"
                        />

                        {{ form.processing ? "Sending..." : "Send Reset Link" }}
                    </Button>
                </form>

                <div class="relative py-2">
                    <div class="absolute inset-0 flex items-center">
                        <span class="w-full border-t"></span>
                    </div>

                    <div class="relative flex justify-center">
                        <span
                            class="bg-card px-3 text-xs text-muted-foreground"
                        >
                            Remembered your password?
                        </span>
                    </div>
                </div>

                <div class="text-center">
                    <Link
                        :href="route('login')"
                        class="inline-flex items-center gap-2 font-semibold text-primary hover:underline"
                    >
                        <ArrowLeft class="h-4 w-4" />

                        Back to Login
                    </Link>
                </div>
            </CardContent>
        </Card>
    </AuthLayout>
</template>
