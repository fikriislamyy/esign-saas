<script setup>
import { ShieldCheck } from "lucide-vue-next";

defineProps({
    collapsed: {
        type: Boolean,
        default: false,
    },

    hideText: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div
        class="flex h-16 items-center border-b border-border/50 px-4"
        :class="collapsed ? 'justify-center' : 'gap-3'"
    >
        <!-- Logo -->

        <div
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm"
        >
            <ShieldCheck class="h-5 w-5" />
        </div>

        <!-- Branding -->

        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 -translate-x-2"
            enter-to-class="opacity-100 translate-x-0"
            leave-active-class="transition-all duration-150 ease-in"
            leave-from-class="opacity-100 translate-x-0"
            leave-to-class="opacity-0 -translate-x-2"
        >
            <div v-if="!hideText" class="min-w-0">
                <h2 class="truncate text-base font-bold tracking-tight">
                    ESign
                </h2>

                <p class="truncate text-xs text-muted-foreground">
                    Secure Digital Signing
                </p>
            </div>
        </Transition>
    </div>
</template>
