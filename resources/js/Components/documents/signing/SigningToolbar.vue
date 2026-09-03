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
        class="flex flex-col gap-3 border-b bg-card p-3 sm:flex-row sm:items-center sm:justify-between"
    >
        <!-- Page navigation -->

        <div class="flex items-center gap-2">
            <Button
                size="icon"
                variant="outline"
                :disabled="currentPage <= 1"
                @click="emit('previousPage')"
            >
                <ChevronLeft class="h-4 w-4" />
            </Button>

            <div
                class="flex h-9 min-w-[90px] items-center justify-center rounded-md border bg-background px-3 text-sm font-medium"
            >
                Page {{ currentPage }} / {{ totalPages }}
            </div>

            <Button
                size="icon"
                variant="outline"
                :disabled="currentPage >= totalPages"
                @click="emit('nextPage')"
            >
                <ChevronRight class="h-4 w-4" />
            </Button>
        </div>

        <!-- Signature navigation -->

        <div v-if="totalFields" class="flex items-center gap-2">
            <Button
                size="icon"
                variant="outline"
                :disabled="currentFieldIndex <= 0"
                @click="emit('previousSignature')"
            >
                <ChevronLeft class="h-4 w-4" />
            </Button>

            <div
                class="flex h-9 min-w-[120px] items-center justify-center gap-2 rounded-md border bg-background px-3 text-sm"
            >
                <LocateFixed class="h-4 w-4 text-muted-foreground" />

                {{ currentFieldIndex + 1 }}
                /
                {{ totalFields }}
            </div>

            <Button
                size="icon"
                variant="outline"
                :disabled="currentFieldIndex >= totalFields - 1"
                @click="emit('nextSignature')"
            >
                <ChevronRight class="h-4 w-4" />
            </Button>
        </div>
    </div>
</template>
