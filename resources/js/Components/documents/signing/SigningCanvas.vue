<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";

import SigningField from "./SigningField.vue";

defineProps({
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

const emit = defineEmits(["sign", "resize"]);

const workspace = ref(null);

let resizeObserver = null;

function emitWorkspaceWidth() {
    if (!workspace.value) {
        return;
    }

    const width = workspace.value.clientWidth;

    if (!width) {
        return;
    }

    emit("resize", width);
}

onMounted(() => {
    emitWorkspaceWidth();

    resizeObserver = new ResizeObserver(() => {
        emitWorkspaceWidth();
    });

    resizeObserver.observe(workspace.value);
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
});
</script>

<template>
    <div ref="workspace" class="w-full overflow-auto bg-muted/20">
        <div class="flex min-h-[65vh] min-w-full justify-center p-3 sm:p-6">
            <div class="relative inline-block shrink-0">
                <!-- PDF -->

                <slot name="canvas" />

                <!-- Signature Fields -->

                <SigningField
                    v-for="field in fields"
                    :key="field.id"
                    :field="field"
                    :canvas-width="canvasWidth"
                    :canvas-height="canvasHeight"
                    @sign="(field) => emit('sign', field)"
                />
            </div>
        </div>
    </div>
</template>
