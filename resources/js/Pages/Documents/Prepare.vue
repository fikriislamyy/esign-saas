<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from "vue";
import axios from "axios";
import { Head, Link, router } from "@inertiajs/vue3";
import * as pdfjsLib from "pdfjs-dist";

import AppLayout from "@/Layouts/AppLayout.vue";

import PageHeader from "@/Components/page/PageHeader.vue";
import PageSection from "@/Components/page/PageSection.vue";

import PrepareSidebar from "@/Components/documents/prepare/PrepareSidebar.vue";
import PrepareToolbar from "@/Components/documents/prepare/PrepareToolbar.vue";
import PdfCanvas from "@/Components/documents/prepare/PdfCanvas.vue";

import LoadingOverlay from "@/Components/feedback/LoadingOverlay.vue";
import FeedbackDialog from "@/Components/feedback/FeedbackDialog.vue";

import { useFeedback } from "@/Composables/useFeedback";

import { Button } from "@/components/ui/button";
import { ArrowLeft, Save } from "lucide-vue-next";
import { hide } from "@unovis/ts/components/free-brush/style";

pdfjsLib.GlobalWorkerOptions.workerSrc = new URL(
    "pdfjs-dist/build/pdf.worker.min.mjs",
    import.meta.url,
).toString();

const props = defineProps({
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

/*
|--------------------------------------------------------------------------
| Editor State
|--------------------------------------------------------------------------
*/

const editor = reactive({
    currentPage: 1,

    totalPages: 0,

    zoom: window.innerWidth < 768 ? 0.7 : 1.2,

    selectedSigner: null,

    placingSignature: false,

    draggingField: null,

    resizingField: null,

    isResizing: false,

    activePointerId: null,

    dragStart: {
        x: 0,
        y: 0,

        mouseX: 0,
        mouseY: 0,
    },

    resizeStart: {
        width: 0,
        height: 0,

        mouseX: 0,
        mouseY: 0,
    },
});

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
    })),
);

/*
|--------------------------------------------------------------------------
| Canvas
|--------------------------------------------------------------------------
*/

const canvasWidth = ref(0);

const canvasHeight = ref(0);

const pdfCanvas = ref(null);

/*
|--------------------------------------------------------------------------
| Fields
|--------------------------------------------------------------------------
*/

function pageFields() {
    return signatureFields.value.filter(
        (field) => Number(field.page) === Number(editor.currentPage),
    );
}

/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

async function nextPage() {
    if (editor.currentPage >= editor.totalPages) {
        return;
    }

    editor.currentPage++;

    await renderPage();
}

async function prevPage() {
    if (editor.currentPage <= 1) {
        return;
    }

    editor.currentPage--;

    await renderPage();
}

/*
|--------------------------------------------------------------------------
| Zoom
|--------------------------------------------------------------------------
*/

async function zoomIn() {
    editor.zoom += 0.2;

    await renderPage();
}

async function zoomOut() {
    if (editor.zoom <= 0.4) {
        return;
    }

    editor.zoom -= 0.2;

    await renderPage();
}

async function resetZoom() {
    editor.zoom = window.innerWidth < 768 ? 0.7 : 1.2;

    await renderPage();
}

function showFeedback(type, title, message) {
    feedback.type = type;
    feedback.title = title;
    feedback.message = message;
    feedback.open = true;
}

async function finishPreparing() {
    if (loading.value) {
        return;
    }

    showLoading("Finishing document preparation...");

    try {
        await axios.post(route("documents.prepare.finish", props.document.id));

        showSuccess(
            "The document has been successfully prepared and is ready for the next step.",
            "Preparation Complete",
            "Continue",
        );
    } catch (error) {
        console.error(error);

        showError(
            error.response?.data?.message ??
                "Something went wrong while finishing document preparation.",
            "Preparation Failed",
            "Close",
        );
    } finally {
        hideLoading();
    }
}

function handleFeedbackClose() {
    const shouldRedirect = feedbackType.value === "success";

    closeFeedback();

    if (shouldRedirect) {
        router.visit(route("documents.show", props.document.id));
    }
}

/*
|--------------------------------------------------------------------------
| PDF Rendering
|--------------------------------------------------------------------------
*/

async function renderPage() {
    if (!pdfDoc) {
        return;
    }

    const page = await pdfDoc.getPage(editor.currentPage);

    const canvas = pdfCanvas.value;

    if (!canvas) {
        console.error("Canvas element not found.");
        return;
    }

    const ctx = canvas.getContext("2d");

    if (!ctx) {
        console.error("Canvas context not found.");
        return;
    }

    const viewport = page.getViewport({
        scale: editor.zoom,
    });

    canvas.width = Math.ceil(viewport.width);
    canvas.height = Math.ceil(viewport.height);

    canvasWidth.value = canvas.width;
    canvasHeight.value = canvas.height;

    await page.render({
        canvasContext: ctx,
        viewport,
    }).promise;

    console.log("PDF page rendered successfully.");
}

/*
|--------------------------------------------------------------------------
| Save Field
|--------------------------------------------------------------------------
*/

async function saveField(field) {
    await axios.patch(route("documents.signature-fields.update", field.id), {
        x: field.x,

        y: field.y,

        width: field.width,

        height: field.height,
    });
}

/*
|--------------------------------------------------------------------------
| Delete Field
|--------------------------------------------------------------------------
*/

async function deleteField(fieldId) {
    await axios.delete(route("documents.signature-fields.destroy", fieldId));

    signatureFields.value = signatureFields.value.filter(
        (field) => field.id !== fieldId,
    );
}

/*
|--------------------------------------------------------------------------
| Place Field
|--------------------------------------------------------------------------
*/

async function placeField(pageNumber, event) {
    showLoading("Placing signature field...");
    if (!editor.placingSignature) {
        return;
    }

    if (!editor.selectedSigner) {
        return;
    }

    const target = event.currentTarget;

    const rect = target.getBoundingClientRect();

    const x = (event.clientX - rect.left) / canvasWidth.value;

    const y = (event.clientY - rect.top) / canvasHeight.value;

    const width = 180 / canvasWidth.value;

    const height = 60 / canvasHeight.value;

    const response = await axios.post(
        route("documents.signature-fields.store", props.document.id),
        {
            signer_id: editor.selectedSigner.id,

            page: pageNumber,

            x,

            y,

            width,

            height,
        },
    );

    signatureFields.value.push({
        ...response.data.field,

        x: Number(response.data.field.x),

        y: Number(response.data.field.y),

        width: Number(response.data.field.width),

        height: Number(response.data.field.height),
    });

    editor.placingSignature = false;

    hideLoading();
}

/*
|--------------------------------------------------------------------------
| Start Drag
|--------------------------------------------------------------------------
*/

function startDrag(field, event) {
    if (event.pointerType === "mouse" && event.button !== 0) {
        return;
    }

    if (editor.isResizing) {
        return;
    }

    event.preventDefault();

    editor.activePointerId = event.pointerId;

    editor.resizingField = null;

    editor.draggingField = field;

    editor.dragStart.x = Number(field.x);

    editor.dragStart.y = Number(field.y);

    editor.dragStart.mouseX = event.clientX;

    editor.dragStart.mouseY = event.clientY;
}

/*
|--------------------------------------------------------------------------
| Start Resize
|--------------------------------------------------------------------------
*/

function startResize(field, event) {
    if (event.pointerType === "mouse" && event.button !== 0) {
        return;
    }

    event.preventDefault();

    event.stopPropagation();

    editor.activePointerId = event.pointerId;

    editor.draggingField = null;

    editor.isResizing = true;

    editor.resizingField = field;

    editor.resizeStart.width = Number(field.width);

    editor.resizeStart.height = Number(field.height);

    editor.resizeStart.mouseX = event.clientX;

    editor.resizeStart.mouseY = event.clientY;
}

/*
|--------------------------------------------------------------------------
| Pointer Move
|--------------------------------------------------------------------------
*/

function onPointerMove(event) {
    if (
        editor.activePointerId !== null &&
        event.pointerId !== editor.activePointerId
    ) {
        return;
    }

    if (!canvasWidth.value || !canvasHeight.value) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Dragging
    |--------------------------------------------------------------------------
    */

    if (editor.draggingField) {
        event.preventDefault();

        const field = editor.draggingField;

        const deltaX =
            (event.clientX - editor.dragStart.mouseX) / canvasWidth.value;

        const deltaY =
            (event.clientY - editor.dragStart.mouseY) / canvasHeight.value;

        const newX = editor.dragStart.x + deltaX;

        const newY = editor.dragStart.y + deltaY;

        field.x = Math.max(0, Math.min(1 - Number(field.width), newX));

        field.y = Math.max(0, Math.min(1 - Number(field.height), newY));

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Resizing
    |--------------------------------------------------------------------------
    */

    if (editor.isResizing && editor.resizingField) {
        event.preventDefault();

        const field = editor.resizingField;

        const deltaX =
            (event.clientX - editor.resizeStart.mouseX) / canvasWidth.value;

        const deltaY =
            (event.clientY - editor.resizeStart.mouseY) / canvasHeight.value;

        const minWidth = 80 / canvasWidth.value;

        const minHeight = 40 / canvasHeight.value;

        const maxWidth = 1 - Number(field.x);

        const maxHeight = 1 - Number(field.y);

        field.width = Math.min(
            maxWidth,
            Math.max(
                minWidth,

                editor.resizeStart.width + deltaX,
            ),
        );

        field.height = Math.min(
            maxHeight,
            Math.max(
                minHeight,

                editor.resizeStart.height + deltaY,
            ),
        );
    }
}

/*
|--------------------------------------------------------------------------
| Stop Interaction
|--------------------------------------------------------------------------
*/

async function stopInteraction(event) {
    if (
        editor.activePointerId !== null &&
        event?.pointerId !== undefined &&
        event.pointerId !== editor.activePointerId
    ) {
        return;
    }

    const field = editor.draggingField ?? editor.resizingField;

    editor.draggingField = null;

    editor.resizingField = null;

    editor.isResizing = false;

    editor.activePointerId = null;

    if (field) {
        await saveField(field);
    }
}

/*
|--------------------------------------------------------------------------
| Mount
|--------------------------------------------------------------------------
*/

onMounted(async () => {
    try {
        showLoading("Loading document...");
        const pdfUrl = route("documents.preview", props.document.id);

        console.log("Loading PDF:", pdfUrl);

        pdfDoc = await pdfjsLib.getDocument({
            url: pdfUrl,
        }).promise;

        console.log("PDF loaded successfully:", pdfDoc.numPages, "pages");

        editor.totalPages = pdfDoc.numPages;

        await renderPage();

        console.log("PDF page rendered successfully.");
        hideLoading();
    } catch (error) {
        console.error("PDF loading/rendering failed:", error);
        hideLoading();
    }

    window.addEventListener("pointermove", onPointerMove, {
        passive: false,
    });

    window.addEventListener("pointerup", stopInteraction);

    window.addEventListener("pointercancel", stopInteraction);
});

/*
|--------------------------------------------------------------------------
| Unmount
|--------------------------------------------------------------------------
*/

onUnmounted(() => {
    window.removeEventListener("pointermove", onPointerMove);

    window.removeEventListener("pointerup", stopInteraction);

    window.removeEventListener("pointercancel", stopInteraction);
});
</script>

<template>
    <Head :title="`Prepare - ${document.name}`" />

    <AppLayout>
        <div class="space-y-8">
            <PageHeader title="Prepare Document" :description="document.name">
                <template #actions>
                    <Button variant="outline" as-child>
                        <Link :href="route('documents.show', document.id)">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back
                        </Link>
                    </Button>

                    <Button :disabled="loading" @click="finishPreparing">
                        <Save class="mr-2 h-4 w-4" />

                        {{ loading ? "Finishing..." : "Finish Preparing" }}
                    </Button>
                </template>
            </PageHeader>

            <!-- Your existing workspace -->

            <div class="grid gap-6 lg:grid-cols-4">
                <!-- Sidebar -->

                <PageSection
                    class="lg:col-span-1"
                    title="Preparation"
                    description="Configure signature fields."
                >
                    <PrepareSidebar
                        :document="document"
                        :editor="editor"
                        :signature-fields="signatureFields"
                        @start-placement="editor.placingSignature = true"
                    />
                </PageSection>

                <!-- Editor -->

                <div class="lg:col-span-3">
                    <div
                        class="overflow-hidden rounded-xl border bg-card shadow-sm"
                    >
                        <PrepareToolbar
                            :editor="editor"
                            @previous="prevPage"
                            @next="nextPage"
                            @zoom-in="zoomIn"
                            @zoom-out="zoomOut"
                            @reset-zoom="resetZoom"
                        />

                        <PdfCanvas
                            :editor="editor"
                            :fields="pageFields()"
                            :canvas-width="canvasWidth"
                            :canvas-height="canvasHeight"
                            @place-field="placeField"
                            @drag-start="startDrag"
                            @resize-start="startResize"
                            @delete-field="deleteField"
                        >
                            <template #canvas>
                                <canvas
                                    ref="pdfCanvas"
                                    id="pdf-canvas"
                                    class="block rounded-lg border bg-white shadow"
                                />
                            </template>
                        </PdfCanvas>
                    </div>
                </div>
            </div>
        </div>

        <LoadingOverlay :show="loading" :text="loadingText" fullscreen />

        <FeedbackDialog
            v-model:open="feedbackOpen"
            :type="feedbackType"
            :title="feedbackTitle"
            :message="feedbackMessage"
            :button-text="feedbackButtonText"
            @close="handleFeedbackClose"
        />
    </AppLayout>
</template>
