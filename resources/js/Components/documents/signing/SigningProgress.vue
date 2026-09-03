<script setup>
import { computed } from "vue";

const props = defineProps({
    signed: {
        type: Number,
        default: 0,
    },

    total: {
        type: Number,
        default: 0,
    },
});

const progress = computed(() => {
    if (!props.total) {
        return 0;
    }

    return Math.round((props.signed / props.total) * 100);
});
</script>

<template>
    <div class="space-y-2">
        <div class="flex items-center justify-between gap-4 text-sm">
            <span class="font-medium"> Signing progress </span>

            <span class="text-muted-foreground"> {{ progress }}% </span>
        </div>

        <div class="h-2 overflow-hidden rounded-full bg-muted">
            <div
                class="h-full rounded-full bg-primary transition-all duration-300"
                :style="{
                    width: `${progress}%`,
                }"
            />
        </div>
    </div>
</template>
