<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";

import { Mail, Lock, Eye, EyeOff, Loader2 } from "lucide-vue-next";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import AuthLayout from "@/Layouts/AuthLayout.vue";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { ref } from "vue";
defineProps({
    canResetPassword: Boolean,
    status: String,
});

const showPassword = ref(false);

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

const submit = () => {
    form.post(route("login"), {
        onFinish: () => form.reset("password"),
    });
};
</script>

<template>
    <Head title="Login" />
    <AuthLayout>
        <Card
            class="w-full max-w-md rounded-2xl border shadow-xl backdrop-blur bg-background/95"
        >
            <CardHeader class="pb-4 text-center">
                <CardTitle class="text-3xl font-bold">
                    Welcome back 👋
                </CardTitle>

                <CardDescription class="text-base">
                    Sign in to continue managing your documents.
                </CardDescription>
            </CardHeader>
            <div
                v-if="status"
                class="mx-6 mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700"
            >
                {{ status }}
            </div>
            <CardContent class="pt-2 pb-8">
                <form @submit.prevent="submit" class="space-y-6 mt-2">
                    <div class="space-y-2">
                        <Label for="email"> Email Address </Label>

                        <div class="relative">
                            <Mail
                                class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground"
                            />

                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                placeholder="you@example.com"
                                autofocus
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

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label for="password"> Password </Label>

                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-sm font-medium text-primary transition-colors hover:underline"
                            >
                                Forgot password?
                            </Link>
                        </div>

                        <div class="relative">
                            <Lock
                                class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground"
                            />

                            <Input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                class="pl-10 pr-10"
                            />

                            <button
                                type="button"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground transition-colors hover:text-foreground"
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

                    <div class="flex items-center gap-2">
                        <Checkbox
                            :checked="form.remember"
                            @update:checked="form.remember = $event"
                        />

                        <Label
                            for="remember"
                            class="cursor-pointer text-sm font-normal"
                        >
                            Remember me
                        </Label>
                    </div>

                    <Button
                        class="w-full h-11 text-base font-semibold"
                        :disabled="form.processing"
                    >
                        <Loader2
                            v-if="form.processing"
                            class="mr-2 h-4 w-4 animate-spin"
                        />

                        {{ form.processing ? "Signing in..." : "Sign In" }}
                    </Button>

                    <div class="relative py-2">
                        <div class="absolute inset-0 flex items-center">
                            <span class="w-full border-t"></span>
                        </div>

                        <div class="relative flex justify-center">
                            <span
                                class="bg-card px-3 text-xs text-muted-foreground"
                            >
                                New here?
                            </span>
                        </div>
                    </div>

                    <div class="space-y-4 text-center">
                        <div>
                            <p class="text-sm text-muted-foreground">
                                Don't have an account?
                            </p>

                            <Link
                                :href="route('register')"
                                class="font-semibold text-primary hover:underline"
                            >
                                Create one
                            </Link>
                        </div>
                    </div>
                </form>
            </CardContent>
        </Card>
    </AuthLayout>
</template>
