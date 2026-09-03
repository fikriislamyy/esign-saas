<script setup>
import SignatureField from "./SignatureField.vue";

const props = defineProps({
    editor: {
        type: Object,
        required: true,
    },

    fields: {
        type: Array,
        default: () => [],
    },

    canvasWidth: {
        type: Number,
        required: true,
    },

    canvasHeight: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits([
    "placeField",
    "dragStart",
    "resizeStart",
    "deleteField",
]);

function handleDragStart(field, event) {
    emit("dragStart", field, event);
}

function handleResizeStart(field, event) {
    emit("resizeStart", field, event);
}

function handleDelete(fieldId) {
    emit("deleteField", fieldId);
}

function handlePlaceField(event) {
    // Don't place another field while interacting
    // with an existing one.
    if (props.editor.draggingField || props.editor.resizingField) {
        return;
    }

    emit("placeField", props.editor.currentPage, event);
}
</script>

<template>
    <div
        class="flex h-[75vh] justify-center overflow-auto bg-muted/20 p-3 sm:p-6"
    >
        <div
            class="pdf-page relative inline-block"
            :class="{
                'cursor-crosshair': editor.placingSignature,
            }"
            @click="handlePlaceField"
        >
            <!-- PDF Canvas -->

            <slot name="canvas" />

            <!-- Signature Fields -->

            <SignatureField
                v-for="field in fields"
                :key="field.id"
                :field="field"
                :canvas-width="canvasWidth"
                :canvas-height="canvasHeight"
                :editor="editor"
                @drag-start="handleDragStart"
                @resize-start="handleResizeStart"
                @delete="handleDelete"
            />

            <!-- Placement Banner -->

            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition duration-150"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-if="editor.placingSignature"
                    class="pointer-events-none absolute inset-x-0 top-4 z-30 flex justify-center px-4"
                >
                    <div
                        class="rounded-full border border-primary/30 bg-background/90 px-4 py-2 text-center text-sm font-medium text-primary shadow-lg backdrop-blur"
                    >
                        Click or tap anywhere on the document to place the
                        signature
                    </div>
                </div>
            </Transition>
        </div>
    </div>
</template>
