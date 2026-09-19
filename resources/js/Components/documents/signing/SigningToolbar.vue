<script setup>
import { ChevronLeft, ChevronRight, LocateFixed } from "lucide-vue-next";

import { Button } from "@/components/ui/button";

defineProps({
    currentPage: {
        type: Number,
        required: true,
    },

    totalPages: {
        type: Number,
        required: true,
    },

    currentFieldIndex: {
        type: Number,
        default: 0,
    },

    totalFields: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits([
    "previousPage",
    "nextPage",
    "previousSignature",
    "nextSignature",
]);
</script>

<template>
    <div
        class="sticky top-0 z-10 flex items-center justify-between gap-2 border-b bg-card/95 p-2 backdrop-blur sm:p-3"
    >
        <!-- Page navigation -->

        <div class="flex items-center gap-2">
            <Button
                size="icon"
                variant="outline"
                class="h-10 w-10"
                :disabled="currentPage <= 1"
                @click="emit('previousPage')" aria-label="Previous page"
            >
                <ChevronLeft class="h-4 w-4" />
            </Button>

            <div
                class="flex h-9 min-w-[72px] items-center justify-center rounded-md border bg-background px-3 text-sm font-medium"
            >
                <span class="hidden sm:inline">Page&nbsp;</span>{{ currentPage }} / {{ totalPages }}
            </div>

            <Button
                size="icon"
                variant="outline"
                class="h-10 w-10"
                :disabled="currentPage >= totalPages"
                @click="emit('nextPage')" aria-label="Next page"
            >
                <ChevronRight class="h-4 w-4" />
            </Button>
        </div>

        <!-- Signature navigation -->

        <div v-if="totalFields" class="flex items-center gap-2">
            <Button
                size="icon"
                variant="outline"
                class="h-10 w-10"
                :disabled="currentFieldIndex <= 0"
                @click="emit('previousSignature')" aria-label="Previous signature"
            >
                <ChevronLeft class="h-4 w-4" />
            </Button>

            <div
                class="flex h-9 min-w-[84px] items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm"
            >
                <LocateFixed class="h-4 w-4 text-muted-foreground" />

                {{ currentFieldIndex + 1 }}
                /
                {{ totalFields }}
            </div>

            <Button
                size="icon"
                variant="outline"
                class="h-10 w-10"
                :disabled="currentFieldIndex >= totalFields - 1"
                @click="emit('nextSignature')" aria-label="Next signature"
            >
                <ChevronRight class="h-4 w-4" />
            </Button>
        </div>
    </div>
</template>
