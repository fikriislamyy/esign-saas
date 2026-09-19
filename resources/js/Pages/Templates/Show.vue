<script setup>
import { ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";

import { LayoutTemplate, Eye, FilePenLine, Trash2 } from "lucide-vue-next";

import AppLayout from "@/Layouts/AppLayout.vue";

import FadeIn from "@/Components/animations/FadeIn.vue";

import PageHeader from "@/Components/page/PageHeader.vue";
import PageSection from "@/Components/page/PageSection.vue";

import TemplateInfo from "@/Components/templates/TemplateInfo.vue";

import LoadingOverlay from "@/Components/feedback/LoadingOverlay.vue";
import FeedbackDialog from "@/Components/feedback/FeedbackDialog.vue";

import { useFeedback } from "@/Composables/useFeedback";

import { Button } from "@/components/ui/button";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";

const props = defineProps({
    template: Object,
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
    closeFeedback,
} = useFeedback();

const confirmingDelete = ref(false);

function previewTemplate() {
    window.open(route("templates.preview", props.template.id), "_blank");
}

function deleteTemplate() {
    confirmingDelete.value = false;

    router.delete(route("templates.destroy", props.template.id), {
        onStart: () => showLoading("Deleting template..."),
        onFinish: () => hideLoading(),
    });
}
</script>

<template>
    <Head :title="template.name" />

    <AppLayout>
        <LoadingOverlay :show="loading" :text="loadingText" fullscreen />
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
                    :title="template.name + ' - Template Details'"
                    description="Manage template information and signature field layout."
                    :icon="LayoutTemplate"
                >
                    <template #actions>
                        <div class="grid gap-2 sm:flex sm:flex-wrap sm:justify-end">
                            <Button variant="outline" @click="previewTemplate">
                                <Eye class="mr-2 h-4 w-4" />
                                Preview
                            </Button>

                            <Button as-child>
                                <Link :href="route('templates.prepare', template.id)">
                                    <FilePenLine class="mr-2 h-4 w-4" />
                                    Prepare Template
                                </Link>
                            </Button>

                            <Button
                                variant="destructive"
                                @click="confirmingDelete = true"
                            >
                                <Trash2 class="mr-2 h-4 w-4" />
                                Delete
                            </Button>
                        </div>
                    </template>
                </PageHeader>

                <AlertDialog v-model:open="confirmingDelete">
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Delete this template?</AlertDialogTitle>
                            <AlertDialogDescription>
                                "{{ template.name }}" and its
                                {{ template.signature_fields_count }}
                                signature field{{ template.signature_fields_count === 1 ? "" : "s" }}
                                will be permanently removed. This cannot be undone.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction @click="deleteTemplate">
                                Delete template
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </FadeIn>

            <!-- Template Information -->

            <FadeIn :delay="200" type="scale">
                <PageSection
                    title="Template Information"
                    description="General information about this template."
                >
                    <TemplateInfo :template="template" />
                </PageSection>
            </FadeIn>
        </div>
    </AppLayout>
</template>
