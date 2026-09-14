<script setup>
import { Card, CardContent } from "@/components/ui/card";

defineProps({
    title: String,
    value: [String, Number],
    subtitle: String,
    icon: Object,
    color: String,
    bg: String,
});
</script>

<template>
    <Card
        class="transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
    >
        <CardContent class="p-6">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-medium text-muted-foreground">
                        {{ title }}
                    </p>

                    <p class="mt-4 text-5xl font-bold tracking-tight">
                        {{ value }}
                    </p>

                    <p class="mt-2 text-xs text-muted-foreground">
                        {{ subtitle }}
                    </p>
                </div>

                <div
                    :class="[
                        'flex h-12 w-12 items-center justify-center rounded-xl',
                        bg,
                    ]"
                >
                    <component :is="icon" class="h-6 w-6" :class="color" />
                </div>
            </div>
        </CardContent>
    </Card>
</template>
