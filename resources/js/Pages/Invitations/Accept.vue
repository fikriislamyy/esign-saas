<script setup>
import { Head, useForm } from "@inertiajs/vue3";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

const props = defineProps({
    invitation: Object,
});

const form = useForm({
    name: "",
    password: "",
    password_confirmation: "",
});

const submit = () => {
    form.post(route("invitations.complete", props.invitation.token));
};
</script>

<template>
    <Head title="Accept Invitation" />

    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="w-full max-w-md border rounded-lg p-6 space-y-6">
            <div>
                <h1 class="text-2xl font-bold">Join Organization</h1>

                <p class="text-muted-foreground mt-2">
                    You've been invited to join
                    <strong>
                        {{ invitation.organization }}
                    </strong>
                </p>
            </div>

            <div class="text-sm text-muted-foreground">
                Email:
                {{ invitation.email }}
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <Input v-model="form.name" placeholder="Your name" />

                <Input
                    v-model="form.password"
                    type="password"
                    placeholder="Password"
                />

                <Input
                    v-model="form.password_confirmation"
                    type="password"
                    placeholder="Confirm password"
                />

                <Button class="w-full" :disabled="form.processing">
                    Join Organization
                </Button>
            </form>
        </div>
    </div>
</template>
