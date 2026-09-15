<script setup>
import { computed } from "vue";
import { X } from "lucide-vue-next";

const props = defineProps({
    field: {
        type: Object,
        required: true,
    },

    editor: {
        type: Object,
        required: true,
    },

    canvasWidth: {
        type: Number,
        required: true,
    },

    canvasHeight: {
        type: Number,
        required: true,
    },

    editable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(["dragStart", "resizeStart", "delete", "assign"]);

function handleClick(event) {
    if (props.field.free) {
        event.stopPropagation();
        emit("assign", props.field);
    }
}

const style = computed(() => ({
    left: `${Number(props.field.x) * props.canvasWidth}px`,
    top: `${Number(props.field.y) * props.canvasHeight}px`,
    width: `${Number(props.field.width) * props.canvasWidth}px`,
    height: `${Number(props.field.height) * props.canvasHeight}px`,
}));

function handleDragStart(event) {
    if (!props.editable) {
        return;
    }

    if (event.pointerType === "mouse" && event.button !== 0) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    emit("dragStart", props.field, event);
}

function handleResizeStart(event) {
    if (!props.editable) {
        return;
    }

    if (event.pointerType === "mouse" && event.button !== 0) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    emit("resizeStart", props.field, event);
}

function handleDelete() {
    emit("delete", props.field.id);
}
</script>

<template>
    <div
        class="absolute select-none rounded-lg border-2 bg-background/90 shadow-sm backdrop-blur-sm transition-shadow hover:shadow-md dark:bg-slate-900/90"
        :class="{
            'cursor-move border-primary': editable && !editor.isResizing,
            'border-primary': !field.free && !editable,
            'cursor-pointer border-dashed border-amber-500 bg-amber-50/90 dark:bg-amber-950/60':
                field.free,
        }"
        :style="{
            ...style,
            touchAction: 'none',
        }"
        @pointerdown.stop="handleDragStart"
        @click.stop="handleClick"
    >
        <!-- Label -->

        <div
            class="pointer-events-none flex h-full items-center justify-center rounded-md px-2 text-center"
        >
            <span class="truncate text-xs font-semibold text-foreground">
                {{ field.free ? "Unassigned" : (field.signer?.name ?? "Signature") }}
            </span>
        </div>

        <!-- Delete -->

        <button
            v-if="editable"
            type="button"
            class="absolute -right-2 -top-2 z-20 flex h-6 w-6 items-center justify-center rounded-full border bg-background text-foreground shadow transition hover:bg-destructive hover:text-white"
            @pointerdown.stop
            @click.stop="handleDelete"
        >
            <X class="h-3 w-3" />
        </button>

        <!-- Resize Handle -->

        <div
            v-if="editable"
            class="absolute bottom-0 right-0 z-20 h-5 w-5 cursor-se-resize rounded-tl bg-primary shadow-sm"
            style="touch-action: none"
            @pointerdown.stop="handleResizeStart"
        />
    </div>
</template>
