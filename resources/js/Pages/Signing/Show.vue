<script setup>
import { computed, nextTick, onMounted, onBeforeUnmount, ref } from "vue";

import { Head } from "@inertiajs/vue3";

import LoadingOverlay from "@/Components/feedback/LoadingOverlay.vue";
import FeedbackDialog from "@/Components/feedback/FeedbackDialog.vue";

import { useFeedback } from "@/Composables/useFeedback";

import axios from "axios";

import * as pdfjsLib from "pdfjs-dist";

import { CheckCircle2, Loader2 } from "lucide-vue-next";

import { Button } from "@/components/ui/button";

import SigningHeader from "@/Components/documents/signing/SigningHeader.vue";

import SigningProgress from "@/Components/documents/signing/SigningProgress.vue";

import SigningToolbar from "@/Components/documents/signing/SigningToolbar.vue";

import SigningCanvas from "@/Components/documents/signing/SigningCanvas.vue";

import SignatureDialog from "@/Components/documents/signing/SignatureDialog.vue";

pdfjsLib.GlobalWorkerOptions.workerSrc = new URL(
    "pdfjs-dist/build/pdf.worker.min.mjs",
    import.meta.url,
).toString();

/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
*/

const props = defineProps({
    signer: {
        type: Object,
        required: true,
    },

    document: {
        type: Object,
        required: true,
    },
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
    closeFeedback,
} = useFeedback();

/*
|--------------------------------------------------------------------------
| PDF
|--------------------------------------------------------------------------
*/

let pdfDoc = null;

const pdfCanvas = ref(null);

const currentPage = ref(1);

const totalPages = ref(0);

const workspaceWidth = ref(0);

const pdfOriginalWidth = ref(0);

const pdfOriginalHeight = ref(0);

const workspacePadding = window.innerWidth < 640 ? 24 : 48;

let renderTask = null;

let resizeTimeout = null;

const canvasWidth = ref(0);

const canvasHeight = ref(0);

/*
|--------------------------------------------------------------------------
| Signing State
|--------------------------------------------------------------------------
*/

const signing = ref(false);

const signatureDialog = ref(false);

const selectedField = ref(null);

const currentFieldIndex = ref(0);

/*
|--------------------------------------------------------------------------
| Signature Fields
|--------------------------------------------------------------------------
*/

const signatureFields = ref(
    (props.document.signature_fields ?? []).map((field) => ({
        ...field,

        x: Number(field.x),

        y: Number(field.y),

        width: Number(field.width),

        height: Number(field.height),

        page: Number(field.page),

        signature: null,
    })),
);

const myFields = computed(() => {
    return signatureFields.value.filter(
        (field) => field.signer_id === props.signer.id,
    );
});

const sortedFields = computed(() => {
    return [...myFields.value].sort((a, b) => {
        if (a.page !== b.page) {
            return a.page - b.page;
        }

        if (a.y !== b.y) {
            return a.y - b.y;
        }

        return a.x - b.x;
    });
});

const pageFields = computed(() => {
    return myFields.value.filter((field) => field.page === currentPage.value);
});

/*
|--------------------------------------------------------------------------
| Progress
|--------------------------------------------------------------------------
*/

const signedCount = computed(() => {
    return myFields.value.filter((field) => field.signature).length;
});

const allFieldsSigned = computed(() => {
    return (
        myFields.value.length > 0 && signedCount.value === myFields.value.length
    );
});

/*
|--------------------------------------------------------------------------
| PDF Rendering
|--------------------------------------------------------------------------
*/

async function renderPage() {
    if (!pdfDoc) {
        return;
    }

    const canvas = pdfCanvas.value;

    if (!canvas) {
        return;
    }

    const page = await pdfDoc.getPage(currentPage.value);

    // Get original PDF dimensions
    const originalViewport = page.getViewport({
        scale: 1,
    });

    // Use measured workspace width when available.
    // Otherwise use the browser width for the initial render.
    const availableWidth = workspaceWidth.value
        ? workspaceWidth.value - 48
        : Math.min(window.innerWidth - 48, 1000);

    const scale = Math.min(
        Math.max(availableWidth, 280) / originalViewport.width,
        1.2,
    );

    const viewport = page.getViewport({
        scale,
    });

    const context = canvas.getContext("2d");

    if (!context) {
        return;
    }

    // Cancel a previous render before starting another.
    if (renderTask) {
        try {
            renderTask.cancel();

            await renderTask.promise;
        } catch (error) {
            if (error?.name !== "RenderingCancelledException") {
                console.error(error);
            }
        }

        renderTask = null;
    }

    canvas.width = Math.floor(viewport.width);
    canvas.height = Math.floor(viewport.height);

    canvasWidth.value = viewport.width;
    canvasHeight.value = viewport.height;

    renderTask = page.render({
        canvasContext: context,
        viewport,
    });

    try {
        await renderTask.promise;
    } catch (error) {
        if (error?.name !== "RenderingCancelledException") {
            console.error("PDF render failed:", error);
        }
    } finally {
        renderTask = null;
    }
}

/*
|--------------------------------------------------------------------------
| Page Navigation
|--------------------------------------------------------------------------
*/

async function nextPage() {
    if (currentPage.value >= totalPages.value) {
        return;
    }

    currentPage.value++;

    await renderPage();
}

async function previousPage() {
    if (currentPage.value <= 1) {
        return;
    }

    currentPage.value--;

    await renderPage();
}

/*
|--------------------------------------------------------------------------
| Signature Navigation
|--------------------------------------------------------------------------
*/

async function goToSignature(index) {
    if (index < 0 || index >= sortedFields.value.length) {
        return;
    }

    currentFieldIndex.value = index;

    currentPage.value = sortedFields.value[index].page;

    await renderPage();
}

async function nextSignature() {
    await goToSignature(currentFieldIndex.value + 1);
}

async function previousSignature() {
    await goToSignature(currentFieldIndex.value - 1);
}

/*
|--------------------------------------------------------------------------
| Signature Dialog
|--------------------------------------------------------------------------
*/

function openSignatureDialog(field) {
    selectedField.value = field;

    const index = sortedFields.value.findIndex((item) => item.id === field.id);

    if (index !== -1) {
        currentFieldIndex.value = index;
    }

    signatureDialog.value = true;
}

function applySignature(image) {
    if (!selectedField.value) {
        return;
    }

    const field = signatureFields.value.find(
        (item) => item.id === selectedField.value.id,
    );

    if (!field) {
        return;
    }

    field.signature = image;

    signatureDialog.value = false;

    selectedField.value = null;

    const nextUnsignedIndex = sortedFields.value.findIndex(
        (item, index) => index > currentFieldIndex.value && !item.signature,
    );

    if (nextUnsignedIndex !== -1) {
        nextTick(() => {
            goToSignature(nextUnsignedIndex);
        });
    }
}

/*
|--------------------------------------------------------------------------
| Finish Signing
|--------------------------------------------------------------------------
*/

async function finishSigning() {
    if (!allFieldsSigned.value) {
        return;
    }

    signing.value = true;

    showLoading("Submitting your signed document...");

    try {
        const signatures = {};

        myFields.value.forEach((field) => {
            signatures[field.id] = {
                image: field.signature,
            };
        });

        await axios.post(route("sign.finish", props.signer.token), {
            signatures,
        });

        showSuccess(
            "Your signature has been submitted successfully.",
            "Document Signed",
            "Continue",
        );
    } catch (error) {
        console.error("Failed to sign document:", error);

        showError(
            error.response?.data?.message ??
                "Failed to sign the document. Please try again.",
            "Signing Failed",
            "Close",
        );
    } finally {
        signing.value = false;
        hideLoading();
    }
}

function handleFeedbackClose() {
    const wasSuccessful = feedbackType.value === "success";

    closeFeedback();

    if (wasSuccessful) {
        window.location.href = route("sign.completed", props.signer.token);
    }
}

function handleWorkspaceResize(width) {
    if (!width) {
        return;
    }

    if (Math.abs(workspaceWidth.value - width) < 2) {
        return;
    }

    workspaceWidth.value = width;

    clearTimeout(resizeTimeout);

    resizeTimeout = setTimeout(() => {
        renderPage();
    }, 150);
}

/*
|--------------------------------------------------------------------------
| Mount
|--------------------------------------------------------------------------
*/

onMounted(async () => {
    const pdfUrl = route("documents.preview", props.document.id);
    showLoading("Loading document...");
    try {
        pdfDoc = await pdfjsLib.getDocument({
            url: pdfUrl,
        }).promise;

        totalPages.value = pdfDoc.numPages;

        if (sortedFields.value.length > 0) {
            currentFieldIndex.value = 0;
            currentPage.value = sortedFields.value[0].page;
        }

        await nextTick();

        // Always perform an initial render.
        await renderPage();
        hideLoading();
    } catch (error) {
        console.error("Failed to load PDF:", error);
        hideLoading();
    }
});

onBeforeUnmount(() => {
    clearTimeout(resizeTimeout);

    if (renderTask) {
        try {
            renderTask.cancel();
        } catch {
            //
        }
    }
});
</script>

<template>
    <Head :title="document.name" />

    <main class="min-h-screen bg-background">
        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-10">
            <!-- Header -->

            <SigningHeader
                :document="document"
                :signer="signer"
                :signed-count="signedCount"
                :total-fields="myFields.length"
            />

            <!-- Progress -->

            <SigningProgress :signed="signedCount" :total="myFields.length" />

            <!-- Signing Workspace -->

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <SigningToolbar
                    :current-page="currentPage"
                    :total-pages="totalPages"
                    :current-field-index="currentFieldIndex"
                    :total-fields="sortedFields.length"
                    @previous-page="previousPage"
                    @next-page="nextPage"
                    @previous-signature="previousSignature"
                    @next-signature="nextSignature"
                />

                <!-- Finish -->

                <div
                    class="flex flex-col gap-3 rounded-xl border bg-card p-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p class="font-medium">
                            {{
                                allFieldsSigned
                                    ? "Document ready to submit"
                                    : "Complete all signature fields"
                            }}
                        </p>

                        <p class="mt-1 text-sm text-muted-foreground">
                            {{
                                allFieldsSigned
                                    ? "Review your signatures, then submit the signed document."
                                    : `${signedCount} of ${myFields.length} required signatures completed.`
                            }}
                        </p>
                    </div>

                    <Button
                        size="lg"
                        class="shrink-0"
                        :disabled="!allFieldsSigned || signing"
                        @click="finishSigning"
                    >
                        <Loader2
                            v-if="signing"
                            class="mr-2 h-4 w-4 animate-spin"
                        />

                        <CheckCircle2 v-else class="mr-2 h-4 w-4" />

                        {{ signing ? "Submitting..." : "Finish Signing" }}
                    </Button>
                </div>

                <SigningCanvas
                    :fields="pageFields"
                    :canvas-width="canvasWidth"
                    :canvas-height="canvasHeight"
                    @sign="openSignatureDialog"
                    @resize="handleWorkspaceResize"
                >
                    <template #canvas>
                        <canvas
                            ref="pdfCanvas"
                            class="block bg-white shadow-sm"
                        />
                    </template>
                </SigningCanvas>
            </div>
        </div>
    </main>

    <!-- Signature Dialog -->

    <SignatureDialog v-model:open="signatureDialog" @save="applySignature" />

    <!-- Loading Overlay -->

    <LoadingOverlay :show="loading" :text="loadingText" fullscreen />

    <!-- Feedback Dialog -->

    <FeedbackDialog
        v-model:open="feedbackOpen"
        :type="feedbackType"
        :title="feedbackTitle"
        :message="feedbackMessage"
        :button-text="feedbackButtonText"
        @close="handleFeedbackClose"
    />
</template>
