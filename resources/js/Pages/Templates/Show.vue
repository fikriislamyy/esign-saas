<script setup>
import { Head, Link } from "@inertiajs/vue3";

import { LayoutTemplate, Eye, FilePenLine } from "lucide-vue-next";

import AppLayout from "@/Layouts/AppLayout.vue";

import FadeIn from "@/Components/animations/FadeIn.vue";

import PageHeader from "@/Components/page/PageHeader.vue";
import PageSection from "@/Components/page/PageSection.vue";

import TemplateInfo from "@/Components/templates/TemplateInfo.vue";

import LoadingOverlay from "@/Components/feedback/LoadingOverlay.vue";
import FeedbackDialog from "@/Components/feedback/FeedbackDialog.vue";

import { useFeedback } from "@/Composables/useFeedback";

import { Button } from "@/components/ui/button";

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

function previewTemplate() {
    window.open(route("templates.preview", props.template.id), "_blank");
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
                        <div class="flex flex-wrap justify-end gap-2">
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
                        </div>
                    </template>
                </PageHeader>
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
