<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import GuestLayout from "@/Layouts/GuestLayout.vue";

const form = useForm({
    password: "",
});

const submit = () => {
    form.post(route("password.confirm"), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Confirm password" />

        <div class="space-y-6">
            <div class="space-y-2">
                <h1 class="text-2xl font-bold">Confirm password</h1>
                <p class="text-sm text-muted-foreground">
                    This is a secure area of the application. Please confirm your password before continuing.
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                        autofocus
                    />
                    <p v-if="form.errors.password" class="text-sm text-destructive">
                        {{ form.errors.password }}
                    </p>
                </div>

                <Button
                    type="submit"
                    :disabled="form.processing"
                    :class="{ 'opacity-50': form.processing }"
                    class="w-full"
                >
                    {{ form.processing ? "Confirming..." : "Confirm" }}
                </Button>
            </form>
        </div>
    </GuestLayout>
</template>
