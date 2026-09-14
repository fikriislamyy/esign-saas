<script setup>
import { RotateCcw } from "lucide-vue-next";

import { Button } from "@/components/ui/button";

import ZoomControls from "./ZoomControls.vue";
import PageNavigator from "./PageNavigator.vue";

defineProps({
    editor: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits([
    "previous",
    "next",
    "zoomIn",
    "zoomOut",
    "resetZoom",
]);
</script>

<template>
    <div
        class="sticky top-0 z-20 flex flex-wrap items-center justify-between gap-4 border-b bg-background/95 p-4 backdrop-blur supports-[backdrop-filter]:bg-background/80"
    >
        <PageNavigator
            :current-page="editor.currentPage"
            :total-pages="editor.totalPages"
            @previous="emit('previous')"
            @next="emit('next')"
        />

        <div class="flex items-center gap-2">
            <ZoomControls
                :editor="editor"
                @zoom-in="emit('zoomIn')"
                @zoom-out="emit('zoomOut')"
            />

            <Button variant="outline" size="icon" @click="emit('resetZoom')">
                <RotateCcw class="h-4 w-4" />
            </Button>
        </div>
    </div>
</template>
