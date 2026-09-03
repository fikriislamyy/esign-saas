<script setup>
import { Head } from "@inertiajs/vue3";
import { UserRound } from "lucide-vue-next";

import AppLayout from "@/Layouts/AppLayout.vue";

import FadeIn from "@/Components/animations/FadeIn.vue";
import PageHeader from "@/Components/page/PageHeader.vue";
import PageSection from "@/Components/page/PageSection.vue";

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
        <div class="max-w-4xl space-y-8">
            <FadeIn :delay="100">
                <PageHeader
                    title="Profile"
                    description="Manage your personal account settings."
                    :icon="UserRound"
                />
            </FadeIn>

            <FadeIn :delay="200" type="scale">
                <PageSection
                    title="Profile Information"
                    description="Update your name and email address."
                >
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                    />
                </PageSection>
            </FadeIn>

            <FadeIn :delay="300" type="scale">
                <PageSection
                    title="Password"
                    description="Change your account password."
                >
                    <UpdatePasswordForm />
                </PageSection>
            </FadeIn>

            <FadeIn :delay="400" type="scale">
                <PageSection
                    title="Danger Zone"
                    description="Permanently delete your account."
                >
                    <DeleteUserForm />
                </PageSection>
            </FadeIn>
        </div>
    </AppLayout>
</template>
