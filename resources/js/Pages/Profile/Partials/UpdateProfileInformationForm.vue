<script setup>
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";

import { Link, useForm, usePage } from "@inertiajs/vue3";

defineProps({
    mustVerifyEmail: Boolean,
    status: String,
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <form
        @submit.prevent="form.patch(route('profile.update'))"
        class="space-y-6"
    >
        <div class="space-y-2">
            <Label for="name"> Name </Label>

            <Input
                id="name"
                v-model="form.name"
                type="text"
                autocomplete="name"
                autofocus
            />

            <p v-if="form.errors.name" class="text-sm text-destructive">
                {{ form.errors.name }}
            </p>
        </div>

        <div class="space-y-2">
            <Label for="email"> Email </Label>

            <Input
                id="email"
                v-model="form.email"
                type="email"
                autocomplete="username"
            />

            <p v-if="form.errors.email" class="text-sm text-destructive">
                {{ form.errors.email }}
            </p>
        </div>

        <div
            v-if="mustVerifyEmail && !user.email_verified_at"
            class="space-y-2"
        >
            <p class="text-sm text-muted-foreground">
                Your email address is unverified.

                <Link
                    :href="route('verification.send')"
                    method="post"
                    as="button"
                    class="underline ml-1"
                >
                    Click here to re-send the verification email.
                </Link>
            </p>

            <p
                v-if="status === 'verification-link-sent'"
                class="text-sm text-green-600"
            >
                A new verification link has been sent to your email address.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <Button type="submit" :disabled="form.processing">
                Save Changes
            </Button>

            <Transition
                enter-active-class="transition ease-in-out duration-300"
                enter-from-class="opacity-0"
                leave-active-class="transition ease-in-out duration-300"
                leave-to-class="opacity-0"
            >
                <p
                    v-if="form.recentlySuccessful"
                    class="text-sm text-muted-foreground"
                >
                    Saved.
                </p>
            </Transition>
        </div>
    </form>
</template>
