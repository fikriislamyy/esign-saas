<script setup>
import { Head, useForm } from "@inertiajs/vue3";

import { FileText } from "lucide-vue-next";

import AppLayout from "@/Layouts/AppLayout.vue";

import PageHeader from "@/Components/page/PageHeader.vue";
import PageSection from "@/Components/page/PageSection.vue";

import UploadCard from "@/Components/documents/UploadCard.vue";
import DocumentTable from "@/Components/documents/DocumentTable.vue";

import FadeIn from "@/Components/animations/FadeIn.vue";

import LoadingOverlay from "@/Components/feedback/LoadingOverlay.vue";
import FeedbackDialog from "@/Components/feedback/FeedbackDialog.vue";

import { useFeedback } from "@/Composables/useFeedback";

defineProps({
    documents: Array,
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
    form.post(route("documents.store"), {
        preserveScroll: true,

        onStart: () => {
            showLoading("Uploading document...");
        },

        onSuccess: () => {
            showSuccess(
                "Your document has been uploaded successfully.",
                "Document Uploaded",
            );

            form.reset("file");
        },

        onError: (errors) => {
            console.error(errors);

            const message =
                errors.file ??
                errors.document ??
                "The document could not be uploaded. Please try again.";

            showError(message, "Upload Failed");
        },

        onFinish: () => {
            hideLoading();
        },
    });
}
</script>

<template>
    <Head title="Documents" />

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
                    title="Documents"
                    description="Manage all documents inside your organization."
                    :icon="FileText"
                />
            </FadeIn>

            <!-- Upload -->

            <FadeIn :delay="200" type="scale">
                <PageSection
                    title="Upload Document"
                    description="Upload PDF or Microsoft Word documents for digital signing."
                >
                    <UploadCard :form="form" @upload="submit" />
                </PageSection>
            </FadeIn>

            <!-- Documents -->

            <FadeIn :delay="300" type="scale">
                <PageSection
                    title="All Documents"
                    description="Browse, search, and manage uploaded documents."
                    :padding="false"
                >
                    <DocumentTable :documents="documents" />
                </PageSection>
            </FadeIn>
        </div>
    </AppLayout>
</template>
