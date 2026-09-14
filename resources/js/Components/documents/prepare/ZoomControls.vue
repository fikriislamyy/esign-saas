<script setup>
import { Minus, Plus } from "lucide-vue-next";

import { Button } from "@/components/ui/button";

const props = defineProps({
    editor: {
        type: Object,
        required: true,
    },

    min: {
        type: Number,
        default: 0.4,
    },

    max: {
        type: Number,
        default: 2,
    },
});

const emit = defineEmits(["zoomIn", "zoomOut"]);
</script>

<template>
    <div class="flex items-center gap-2">
        <Button
            variant="outline"
            size="icon"
            :disabled="editor.zoom <= min"
            @click="emit('zoomOut')"
        >
            <Minus class="h-4 w-4" />
        </Button>

        <div
            class="flex h-9 min-w-[72px] items-center justify-center rounded-md border bg-muted/30 px-3 text-sm font-medium"
        >
            {{ Math.round(editor.zoom * 100) }}%
        </div>

        <Button
            variant="outline"
            size="icon"
            :disabled="editor.zoom >= max"
            @click="emit('zoomIn')"
        >
            <Plus class="h-4 w-4" />
        </Button>
    </div>
</template>
