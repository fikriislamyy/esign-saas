<script setup>
import { Head } from "@inertiajs/vue3";

import AppLayout from "@/Layouts/AppLayout.vue";

import UpdateProfileInformationForm from "./Partials/UpdateProfileInformationForm.vue";
import UpdatePasswordForm from "./Partials/UpdatePasswordForm.vue";
import DeleteUserForm from "./Partials/DeleteUserForm.vue";

defineProps({
    mustVerifyEmail: Boolean,
    status: String,
});
</script>

<template>
    <Head title="Profile" />

    <AppLayout>
        <div class="space-y-6 max-w-4xl">
            <div>
                <h1 class="text-3xl font-bold">Profile</h1>

                <p class="text-muted-foreground">
                    Manage your account settings.
                </p>
            </div>

            <UpdateProfileInformationForm
                :must-verify-email="mustVerifyEmail"
                :status="status"
            />

            <UpdatePasswordForm />

            <DeleteUserForm />
        </div>
    </AppLayout>
</template>
