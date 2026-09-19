<script setup>
import { computed } from "vue";
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

const props = defineProps({
    templates: Array,
    templateQuota: Object,
});

const atLimit = computed(
    () =>
        props.templateQuota.limit !== null &&
        props.templateQuota.used >= props.templateQuota.limit,
);

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
                    :description="`${templateQuota.used} of ${templateQuota.limit} templates used.`"
                >
                    <div
                        v-if="atLimit"
                        class="rounded-xl border border-dashed p-10 text-center"
                    >
                        <LayoutTemplate
                            class="mx-auto mb-3 h-10 w-10 text-muted-foreground"
                        />

                        <h3 class="font-medium">Template limit reached</h3>

                        <p
                            class="mx-auto mt-1 max-w-sm text-sm text-muted-foreground"
                        >
                            Your organization can store up to
                            {{ templateQuota.limit }} templates. Delete one below
                            to upload another.
                        </p>
                    </div>

                    <UploadCard v-else :form="form" @upload="submit" />
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
