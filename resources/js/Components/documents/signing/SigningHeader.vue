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
        class="flex flex-col gap-5 border-b pb-6 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="flex items-start gap-4">
            <div
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border bg-muted/40"
            >
                <FileSignature class="h-6 w-6 text-primary" />
            </div>

            <div class="min-w-0">
                <h1 class="truncate text-2xl font-bold tracking-tight">
                    {{ document.name }}
                </h1>

                <p class="mt-1 text-sm text-muted-foreground">
                    Signature requested for
                    <span class="font-medium text-foreground">
                        {{ signer.name }}
                    </span>
                </p>
            </div>
        </div>

        <Badge variant="outline" class="w-fit gap-2 px-3 py-1.5">
            <CheckCircle2 class="h-4 w-4" />

            {{ signedCount }} of {{ totalFields }} completed
        </Badge>
    </div>
</template>
