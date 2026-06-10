<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";

defineProps({
    canResetPassword: Boolean,
    status: String,
});

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

    <div
        class="min-h-screen flex items-center justify-center bg-background px-4"
    >
        <Card class="w-full max-w-md">
            <CardHeader>
                <CardTitle>Login</CardTitle>
                <CardDescription>
                    Sign in to your organization
                </CardDescription>
            </CardHeader>

            <CardContent>
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <Label>Email</Label>

                        <Input
                            v-model="form.email"
                            type="email"
                            placeholder="you@example.com"
                        />

                        <p
                            v-if="form.errors.email"
                            class="text-sm text-red-500 mt-1"
                        >
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div>
                        <Label>Password</Label>

                        <Input v-model="form.password" type="password" />

                        <p
                            v-if="form.errors.password"
                            class="text-sm text-red-500 mt-1"
                        >
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox
                            :checked="form.remember"
                            @update:checked="form.remember = $event"
                        />

                        <span class="text-sm"> Remember me </span>
                    </div>

                    <Button class="w-full" :disabled="form.processing">
                        Login
                    </Button>

                    <div class="flex justify-between text-sm">
                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                        >
                            Forgot password?
                        </Link>

                        <Link :href="route('register')"> Register </Link>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
