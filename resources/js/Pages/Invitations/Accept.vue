<script setup>
import { ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";

import {
    Building2,
    User,
    Mail,
    Lock,
    Eye,
    EyeOff,
    Loader2,
    ShieldCheck,
} from "lucide-vue-next";

import AuthLayout from "@/Layouts/AuthLayout.vue";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";

const props = defineProps({
    invitation: {
        type: Object,
        required: true,
    },
});

const showPassword = ref(false);
const showConfirmPassword = ref(false);

const form = useForm({
    name: "",
    password: "",
    password_confirmation: "",
});

const submit = () => {
    form.post(route("invitations.complete", props.invitation.token), {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
};
</script>

<template>
    <Head title="Accept Invitation" />

    <AuthLayout>
        <Card
            class="w-full max-w-md rounded-2xl border bg-background/95 shadow-xl backdrop-blur"
        >
            <CardHeader class="pb-4 text-center">
                <div
                    class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10"
                >
                    <Building2 class="h-7 w-7 text-accent-ink" />
                </div>

                <CardTitle class="text-3xl font-bold">
                    You're invited
                </CardTitle>

                <CardDescription class="text-base">
                    Join
                    <span class="font-semibold text-foreground">
                        {{ invitation.organization }}
                    </span>
                    on EZSign.
                </CardDescription>
            </CardHeader>

            <CardContent class="pb-8 pt-2">
                <!-- Invitation summary -->

                <div class="mb-6 space-y-3 rounded-xl border bg-muted/30 p-4">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="text-muted-foreground">Organization</span>

                        <span class="truncate font-medium">
                            {{ invitation.organization }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="text-muted-foreground">Your role</span>

                        <Badge variant="secondary" class="capitalize">
                            {{ invitation.role }}
                        </Badge>
                    </div>
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <!-- Email (locked) -->

                    <div class="space-y-2">
                        <Label for="email">Email Address</Label>

                        <div class="relative">
                            <Mail
                                class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />

                            <Input
                                id="email"
                                :model-value="invitation.email"
                                type="email"
                                disabled
                                class="pl-10"
                            />
                        </div>

                        <p class="text-xs text-muted-foreground">
                            This invitation is tied to this email address.
                        </p>
                    </div>

                    <!-- Name -->

                    <div class="space-y-2">
                        <Label for="name">Full Name</Label>

                        <div class="relative">
                            <User
                                class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />

                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="John Doe"
                                autocomplete="name"
                                autofocus
                                class="pl-10"
                            />
                        </div>

                        <p v-if="form.errors.name" class="text-sm text-destructive">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Password -->

                    <div class="space-y-2">
                        <Label for="password">Password</Label>

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

                        <p v-if="form.errors.password" class="text-sm text-destructive">
                            {{ form.errors.password }}
                        </p>

                        <p v-else class="text-xs text-muted-foreground">
                            Minimum 8 characters.
                        </p>
                    </div>

                    <!-- Confirm password -->

                    <div class="space-y-2">
                        <Label for="password_confirmation">Confirm Password</Label>

                        <div class="relative">
                            <Lock
                                class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />

                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                :type="showConfirmPassword ? 'text' : 'password'"
                                autocomplete="new-password"
                                class="pl-10 pr-10"
                            />

                            <button
                                type="button"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                @click="showConfirmPassword = !showConfirmPassword"
                            >
                                <Eye v-if="!showConfirmPassword" class="h-4 w-4" />
                                <EyeOff v-else class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <Button
                        type="submit"
                        size="lg"
                        class="w-full"
                        :disabled="form.processing"
                    >
                        <Loader2
                            v-if="form.processing"
                            class="mr-2 h-4 w-4 animate-spin"
                        />

                        {{ form.processing ? "Joining..." : "Join Organization" }}
                    </Button>

                    <p
                        class="flex items-center justify-center gap-2 text-center text-xs text-muted-foreground"
                    >
                        <ShieldCheck class="h-3.5 w-3.5" />
                        Your account is protected with bank-level security.
                    </p>
                </form>
            </CardContent>
        </Card>
    </AuthLayout>
</template>
