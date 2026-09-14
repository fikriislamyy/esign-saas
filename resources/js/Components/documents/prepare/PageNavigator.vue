<script setup>
import { ChevronLeft, ChevronRight } from "lucide-vue-next";

import { Button } from "@/components/ui/button";

const props = defineProps({
    currentPage: {
        type: Number,
        required: true,
    },

    totalPages: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(["previous", "next"]);
</script>

<template>
    <div class="flex items-center gap-3">
        <Button
            variant="outline"
            size="icon"
            :disabled="currentPage <= 1"
            @click="emit('previous')"
        >
            <ChevronLeft class="h-4 w-4" />
        </Button>

        <div
            class="flex min-w-[110px] items-center justify-center rounded-md border bg-muted/30 px-4 py-2"
        >
            <span class="text-sm font-medium"> Page {{ currentPage }} </span>

            <span class="mx-2 text-muted-foreground"> / </span>

            <span class="text-sm text-muted-foreground">
                {{ totalPages }}
            </span>
        </div>

        <Button
            variant="outline"
            size="icon"
            :disabled="currentPage >= totalPages"
            @click="emit('next')"
        >
            <ChevronRight class="h-4 w-4" />
        </Button>
    </div>
</template>
