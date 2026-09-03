<script setup>
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";

const props = defineProps({
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
    <Card class="overflow-hidden">
        <CardHeader
            v-if="title || description || $slots.headerActions"
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="space-y-1">
                <CardTitle v-if="title">
                    {{ title }}
                </CardTitle>

                <CardDescription v-if="description">
                    {{ description }}
                </CardDescription>
            </div>

            <div
                v-if="$slots.headerActions"
                class="flex shrink-0 items-center gap-2"
            >
                <slot name="headerActions" />
            </div>
        </CardHeader>

        <CardContent :class="{ 'p-0': !padding }">
            <slot />
        </CardContent>
    </Card>
</template>
