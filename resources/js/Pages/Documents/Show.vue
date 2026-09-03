<script setup>
import { Head, router, useForm } from "@inertiajs/vue3";

import { FileText } from "lucide-vue-next";

import AppLayout from "@/Layouts/AppLayout.vue";

import FadeIn from "@/Components/animations/FadeIn.vue";

import PageHeader from "@/Components/page/PageHeader.vue";
import PageSection from "@/Components/page/PageSection.vue";

import DocumentActions from "@/Components/documents/DocumentActions.vue";
import DocumentInfo from "@/Components/documents/DocumentInfo.vue";
import DocumentPreparation from "@/Components/documents/DocumentPreparation.vue";

import SignersSection from "@/Components/documents/signers/SignersSection.vue";

import LoadingOverlay from "@/Components/feedback/LoadingOverlay.vue";
import FeedbackDialog from "@/Components/feedback/FeedbackDialog.vue";

import { useFeedback } from "@/Composables/useFeedback";

const props = defineProps({
    document: Object,

    members: Array,

    signerFieldCounts: Object,

    canSendForSignature: Boolean,
});

const sendForm = useForm({});

const {
    loading,
    loadingText,

    feedbackOpen,
    feedbackType,
    feedbackTitle,
    feedbackMessage,
    feedbackButtonText,

    confirmationOpen,
    confirmationTitle,
    confirmationMessage,
    confirmationButtonText,
    confirmationCancelText,

    showLoading,
    hideLoading,
    showSuccess,
    showError,
    showConfirmation,
    closeFeedback,
    closeConfirmation,
    confirmAction,
} = useFeedback();

function sendForSignature() {
    showConfirmation({
        title: "Send for Signature?",
        message:
            "Are you sure you want to send this document for signature? Once sent, the signing workflow will begin.",
        confirmText: "Send Document",
        cancelText: "Cancel",

        onConfirm: () => {
            sendDocument();
        },
    });
}

function sendDocument() {
    sendForm.post(route("documents.send", props.document.id), {
        preserveScroll: true,
        preserveState: true,

        onStart: () => {
            showLoading("Sending document for signature...");
        },

        onSuccess: () => {
            showSuccess(
                "The document has been sent successfully. Signers will receive their signing invitations.",
                "Document Sent",
            );
        },

        onError: (errors) => {
            console.error(errors);

            showError(
                errors.document ??
                    "The document could not be sent for signature.",
                "Unable to Send",
            );
        },

        onFinish: () => {
            hideLoading();
        },
    });
}

function previewDocument() {
    window.open(route("documents.preview", props.document.id), "_blank");
}

function downloadDocument() {
    window.location.href = route("documents.download", props.document.id);
}
</script>

<template>
    <Head :title="document.name" />

    <AppLayout>
        <!-- Confirmation -->

        <FeedbackDialog
            v-model:open="confirmationOpen"
            type="confirmation"
            :title="confirmationTitle"
            :message="confirmationMessage"
            :button-text="confirmationButtonText"
            :cancel-text="confirmationCancelText"
            @confirm="confirmAction"
            @close="closeConfirmation"
        />

        <!-- Result -->

        <FeedbackDialog
            v-model:open="feedbackOpen"
            :type="feedbackType"
            :title="feedbackTitle"
            :message="feedbackMessage"
            :button-text="feedbackButtonText"
            @close="closeFeedback"
        />
        <div class="space-y-8">
            <!-- Header -->

            <FadeIn :delay="100" type="fade">
                <PageHeader
                    :title="document.name + ' - Document Details'"
                    description="Manage document information and signing workflow."
                    :icon="FileText"
                >
                    <template #actions>
                        <DocumentActions
                            :document="document"
                            :can-send="canSendForSignature"
                            @send="sendForSignature"
                            @preview="previewDocument"
                            @download="downloadDocument"
                        />
                    </template>
                </PageHeader>
            </FadeIn>

            <!-- Dashboard -->

            <FadeIn :delay="200" type="scale">
                <div class="grid gap-6 lg:grid-cols-5">
                    <PageSection
                        class="lg:col-span-3"
                        title="Document Information"
                        description="General information about this document."
                    >
                        <DocumentInfo :document="document" />
                    </PageSection>

                    <PageSection
                        class="lg:col-span-2"
                        title="Preparation Status"
                        description="Verify every signer is ready before sending."
                    >
                        <DocumentPreparation
                            :document="document"
                            :signer-field-counts="signerFieldCounts"
                        />
                    </PageSection>
                </div>
            </FadeIn>

            <!-- Signers -->

            <FadeIn :delay="300" type="scale">
                <PageSection
                    title="Signers"
                    description="Manage recipients and signing order."
                >
                    <SignersSection
                        :document="document"
                        :members="members"
                        :signer-field-counts="signerFieldCounts"
                    />
                </PageSection>
            </FadeIn>
        </div>
    </AppLayout>
</template>
