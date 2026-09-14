<script setup>
import { ref } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

import {
    Building2,
    User,
    Mail,
    Lock,
    Eye,
    EyeOff,
    Loader2,
} from "lucide-vue-next";

import AuthLayout from "@/Layouts/AuthLayout.vue";

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

const showPassword = ref(false);
const showConfirmPassword = ref(false);

const form = useForm({
    organization_name: "",
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
});

const submit = () => {
    form.post(route("register"), {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
};
</script>

<template>
    <Head title="Register" />

    <AuthLayout>
        <Card
            class="w-full max-w-md rounded-2xl border bg-background/95 shadow-xl backdrop-blur"
        >
            <CardHeader class="pb-4 text-center">
                <CardTitle class="text-3xl font-bold">
                    Create your workspace 🚀
                </CardTitle>

                <CardDescription class="text-base">
                    Start signing documents in minutes.
                </CardDescription>
            </CardHeader>

            <CardContent class="pt-2 pb-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Workspace -->
                    <div class="space-y-4">
                        <div>
                            <h3 class="font-semibold">Workspace</h3>

                            <p class="text-sm text-muted-foreground">
                                Create your team's workspace.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="organization">
                                Organization Name
                            </Label>

                            <div class="relative">
                                <Building2
                                    class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground"
                                />

                                <Input
                                    id="organization"
                                    v-model="form.organization_name"
                                    placeholder="Acme Inc."
                                    class="pl-10"
                                />
                            </div>

                            <p
                                v-if="form.errors.organization_name"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.organization_name }}
                            </p>

                            <p class="text-xs text-muted-foreground">
                                This will become your team's workspace.
                            </p>
                        </div>
                    </div>

                    <div class="border-t"></div>

                    <!-- Account -->
                    <div class="space-y-4">
                        <div>
                            <h3 class="font-semibold">Your Account</h3>

                            <p class="text-sm text-muted-foreground">
                                Tell us a little about yourself.
                            </p>
                        </div>

                        <!-- Name -->
                        <div class="space-y-2">
                            <Label for="name"> Full Name </Label>

                            <div class="relative">
                                <User
                                    class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground"
                                />

                                <Input
                                    id="name"
                                    v-model="form.name"
                                    placeholder="John Doe"
                                    class="pl-10"
                                />
                            </div>

                            <p
                                v-if="form.errors.name"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <!-- Email -->
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
                                    placeholder="john@example.com"
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

                        <!-- Password -->
                        <div class="space-y-2">
                            <Label for="password"> Password </Label>

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
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    @click="showPassword = !showPassword"
                                >
                                    <Eye v-if="!showPassword" class="h-4 w-4" />

                                    <EyeOff v-else class="h-4 w-4" />
                                </button>
                            </div>

                            <p class="text-xs text-muted-foreground">
                                Minimum 8 characters.
                            </p>

                            <p
                                v-if="form.errors.password"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <!-- Confirm Password -->
                        <div class="space-y-2">
                            <Label for="confirm_password">
                                Confirm Password
                            </Label>

                            <div class="relative">
                                <Lock
                                    class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground"
                                />

                                <Input
                                    id="confirm_password"
                                    v-model="form.password_confirmation"
                                    :type="
                                        showConfirmPassword
                                            ? 'text'
                                            : 'password'
                                    "
                                    class="pl-10 pr-10"
                                />

                                <button
                                    type="button"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    @click="
                                        showConfirmPassword =
                                            !showConfirmPassword
                                    "
                                >
                                    <Eye
                                        v-if="!showConfirmPassword"
                                        class="h-4 w-4"
                                    />

                                    <EyeOff v-else class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <Button
                        class="h-11 w-full text-base font-semibold"
                        :disabled="form.processing"
                    >
                        <Loader2
                            v-if="form.processing"
                            class="mr-2 h-4 w-4 animate-spin"
                        />

                        {{
                            form.processing
                                ? "Creating workspace..."
                                : "Create Workspace"
                        }}
                    </Button>

                    <div class="relative py-4">
                        <div class="absolute inset-0 flex items-center">
                            <span class="w-full border-t"></span>
                        </div>

                        <div class="relative flex justify-center">
                            <span
                                class="bg-card px-3 text-xs text-muted-foreground"
                            >
                                Already registered?
                            </span>
                        </div>
                    </div>

                    <div class="text-center">
                        <p class="text-sm text-muted-foreground">
                            Already have an account?
                        </p>

                        <Link
                            :href="route('login')"
                            class="font-semibold text-primary hover:underline"
                        >
                            Sign In
                        </Link>
                    </div>
                </form>
            </CardContent>
        </Card>
    </AuthLayout>
</template>
