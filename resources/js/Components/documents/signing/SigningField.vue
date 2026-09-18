<script setup>
import { computed } from "vue";

import { CheckCircle2, PenLine } from "lucide-vue-next";

const props = defineProps({
    field: {
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

    isActive: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["sign"]);

const style = computed(() => ({
    left: `${Number(props.field.x) * props.canvasWidth}px`,
    top: `${Number(props.field.y) * props.canvasHeight}px`,
    width: `${Number(props.field.width) * props.canvasWidth}px`,
    height: `${Number(props.field.height) * props.canvasHeight}px`,
}));
</script>

<template>
    <button
        type="button"
        class="absolute flex items-center justify-center overflow-hidden rounded-lg border-2 border-border bg-muted text-muted-foreground shadow-sm transition-all duration-200 hover:bg-muted hover:shadow-md focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2"
        :class="{
            'border-border bg-muted': field.signature,
            'animate-pulse border-border': isActive && !field.signature,
        }"
        :style="style"
        @click.stop="emit('sign', field)"
    >
        <!-- Signed Signature -->

        <img
            v-if="field.signature"
            :src="field.signature"
            class="pointer-events-none absolute inset-0 h-full w-full object-contain p-1"
            alt="Signature"
        />

        <!-- Unsigned -->

        <div
            v-else
            class="pointer-events-none flex items-center justify-center gap-2 px-3 text-center text-sm font-semibold text-muted-foreground"
        >
            <PenLine class="h-4 w-4 shrink-0 text-muted-foreground" />

            <span> Click to Sign </span>
        </div>

        <!-- Signed Indicator -->

        <div
            v-if="field.signature"
            class="absolute right-1 top-1 flex h-6 w-6 items-center justify-center rounded-full border border-border bg-white shadow-sm"
        >
            <CheckCircle2 class="h-4 w-4 text-pulse-green" />
        </div>
    </button>
</template>
