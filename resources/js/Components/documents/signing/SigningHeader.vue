<script setup>
import { FileSignature, CheckCircle2 } from "lucide-vue-next";

import { Badge } from "@/components/ui/badge";

defineProps({
    document: {
        type: Object,
        required: true,
    },

    signer: {
        type: Object,
        required: true,
    },

    signedCount: {
        type: Number,
        default: 0,
    },

    totalFields: {
        type: Number,
        default: 0,
    },
});
</script>

<template>
    <div
        class="flex flex-col gap-4 border-b pb-4 sm:flex-row sm:items-center sm:justify-between sm:pb-6 sm:gap-5"
    >
        <div class="flex items-start gap-3 sm:gap-4">
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border bg-muted/40 sm:h-12 sm:w-12 sm:rounded-xl"
            >
                <FileSignature class="h-5 w-5 text-accent-ink sm:h-6 sm:w-6" />
            </div>

            <div class="min-w-0">
                <h1 class="truncate text-xl font-bold tracking-tight sm:text-2xl">
                    {{ document.name }}
                </h1>

                <p class="mt-0.5 text-xs text-muted-foreground sm:mt-1 sm:text-sm">
                    Signature requested for
                    <span class="font-medium text-foreground">
                        {{ signer.name }}
                    </span>
                </p>
            </div>
        </div>

        <Badge variant="outline" class="w-fit gap-2 px-2.5 py-1 text-xs sm:px-3 sm:py-1.5 sm:text-sm">
            <CheckCircle2 class="h-3.5 w-3.5 sm:h-4 sm:w-4" />

            <span class="whitespace-nowrap">{{ signedCount }}/{{ totalFields }}</span>
        </Badge>
    </div>
</template>
