<script setup>
import { useForm } from "@inertiajs/vue3";
import { ref } from "vue";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
});

const updatePassword = () => {
    form.put(route("password.update"), {
        preserveScroll: true,

        onSuccess: () => form.reset(),

        onError: () => {
            if (form.errors.password) {
                form.reset("password", "password_confirmation");

                passwordInput.value?.focus();
            }

            if (form.errors.current_password) {
                form.reset("current_password");

                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle> Update Password </CardTitle>

            <p class="text-sm text-muted-foreground">
                Ensure your account is using a strong password.
            </p>
        </CardHeader>

        <CardContent>
            <form @submit.prevent="updatePassword" class="space-y-6">
                <div class="space-y-2">
                    <Label for="current_password"> Current Password </Label>

                    <Input
                        id="current_password"
                        ref="currentPasswordInput"
                        v-model="form.current_password"
                        type="password"
                        autocomplete="current-password"
                    />

                    <p
                        v-if="form.errors.current_password"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.current_password }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="password"> New Password </Label>

                    <Input
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                    />

                    <p
                        v-if="form.errors.password"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.password }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="password_confirmation">
                        Confirm Password
                    </Label>

                    <Input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                    />

                    <p
                        v-if="form.errors.password_confirmation"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.password_confirmation }}
                    </p>
                </div>

                <div class="flex items-center gap-4">
                    <Button type="submit" :disabled="form.processing">
                        Update Password
                    </Button>

                    <span
                        v-if="form.recentlySuccessful"
                        class="text-sm text-muted-foreground"
                    >
                        Password updated successfully.
                    </span>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
