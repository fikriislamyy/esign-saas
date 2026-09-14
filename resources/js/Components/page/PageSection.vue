<script setup>
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";

defineProps({
    title: {
        type: String,
        default: "",
    },

    description: {
        type: String,
        default: "",
    },

    padding: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <Card class="min-w-0 overflow-hidden">
        <CardHeader
            v-if="title || description || $slots.headerActions"
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <!-- Text -->
            <div class="min-w-0 flex-1 space-y-1">
                <CardTitle class="break-words">
                    {{ title }}
                </CardTitle>

                <CardDescription v-if="description" class="break-words">
                    {{ description }}
                </CardDescription>
            </div>

            <!-- Actions -->
            <div
                v-if="$slots.headerActions"
                class="flex shrink-0 flex-wrap items-center gap-2"
            >
                <slot name="headerActions" />
            </div>
        </CardHeader>

        <CardContent :class="['min-w-0', { 'p-0': !padding }]">
            <slot />
        </CardContent>
    </Card>
</template>
