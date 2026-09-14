<script setup>
import { Loader2 } from "lucide-vue-next";

defineProps({
    show: {
        type: Boolean,
        default: false,
    },

    text: {
        type: String,
        default: "Loading...",
    },

    fullscreen: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <Transition
        enter-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="show"
            :class="[
                'z-50 flex items-center justify-center bg-background/70 backdrop-blur-sm',
                fullscreen ? 'fixed inset-0' : 'absolute inset-0 rounded-xl',
            ]"
        >
            <div
                class="flex min-w-[180px] flex-col items-center gap-3 rounded-xl border bg-card px-6 py-5 shadow-lg"
            >
                <Loader2 class="h-6 w-6 animate-spin text-primary" />

                <p class="text-sm font-medium text-foreground">
                    {{ text }}
                </p>
            </div>
        </div>
    </Transition>
</template>
