<script setup>
import { Head, useForm } from "@inertiajs/vue3";

import { LayoutTemplate } from "lucide-vue-next";

import AppLayout from "@/Layouts/AppLayout.vue";

import PageHeader from "@/Components/page/PageHeader.vue";
import PageSection from "@/Components/page/PageSection.vue";

import UploadCard from "@/Components/documents/UploadCard.vue";
import TemplateTable from "@/Components/templates/TemplateTable.vue";

import FadeIn from "@/Components/animations/FadeIn.vue";

import LoadingOverlay from "@/Components/feedback/LoadingOverlay.vue";
import FeedbackDialog from "@/Components/feedback/FeedbackDialog.vue";

import { useFeedback } from "@/Composables/useFeedback";

defineProps({
    templates: Array,
});

const form = useForm({
    file: null,
});

const {
    loading,
    loadingText,

    feedbackOpen,
    feedbackType,
    feedbackTitle,
    feedbackMessage,
    feedbackButtonText,

    showLoading,
    hideLoading,
    showSuccess,
    showError,
} = useFeedback();

function submit() {
    form.post(route("templates.store"), {
        preserveScroll: true,

        onStart: () => {
            showLoading("Uploading template...");
        },

        onSuccess: () => {
            showSuccess(
                "Your template has been uploaded successfully.",
                "Template Uploaded",
            );

            form.reset("file");
        },

        onError: (errors) => {
            console.error(errors);

            const message =
                errors.file ??
                errors.template ??
                "The template could not be uploaded. Please try again.";

            showError(message, "Upload Failed");
        },

        onFinish: () => {
            hideLoading();
        },
    });
}
</script>

<template>
    <Head title="Templates" />

    <AppLayout>
        <!-- Loading -->

        <LoadingOverlay :show="loading" :text="loadingText" fullscreen />

        <!-- Feedback -->

        <FeedbackDialog
            v-model:open="feedbackOpen"
            :type="feedbackType"
            :title="feedbackTitle"
            :message="feedbackMessage"
            :button-text="feedbackButtonText"
        />

        <div class="space-y-8">
            <!-- Page Header -->

            <FadeIn :delay="100" type="fade">
                <PageHeader
                    title="Templates"
                    description="Reusable documents with pre-placed signature fields."
                    :icon="LayoutTemplate"
                />
            </FadeIn>

            <!-- Upload -->

            <FadeIn :delay="200" type="scale">
                <PageSection
                    title="Upload Template"
                    description="Upload a PDF to use as a template."
                >
                    <UploadCard :form="form" @upload="submit" />
                </PageSection>
            </FadeIn>

            <!-- Templates -->

            <FadeIn :delay="300" type="scale">
                <PageSection
                    title="All Templates"
                    description="Browse and manage your organization's templates."
                    :padding="false"
                >
                    <TemplateTable :templates="templates" />
                </PageSection>
            </FadeIn>
        </div>
    </AppLayout>
</template>
