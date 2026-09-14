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
        class="absolute flex items-center justify-center overflow-hidden rounded-lg border-2 border-slate-500 bg-slate-100 text-slate-900 shadow-sm transition-all duration-200 hover:bg-slate-200 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2"
        :class="{
            'border-slate-400 bg-slate-50': field.signature,
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
            class="pointer-events-none flex items-center justify-center gap-2 px-3 text-center text-sm font-semibold text-slate-900"
        >
            <PenLine class="h-4 w-4 shrink-0 text-slate-900" />

            <span> Click to Sign </span>
        </div>

        <!-- Signed Indicator -->

        <div
            v-if="field.signature"
            class="absolute right-1 top-1 flex h-6 w-6 items-center justify-center rounded-full border border-slate-200 bg-white shadow-sm"
        >
            <CheckCircle2 class="h-4 w-4 text-green-600" />
        </div>
    </button>
</template>
