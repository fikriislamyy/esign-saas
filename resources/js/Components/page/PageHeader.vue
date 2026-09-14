<script setup>
const props = defineProps({
    title: {
        type: String,
        required: true,
    },

    description: {
        type: String,
        default: "",
    },

    icon: {
        type: [Object, Function],
        default: null,
    },
});
</script>

<template>
    <div
        class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between"
    >
        <!-- Left -->

        <div class="flex items-start gap-4">
            <div
                v-if="icon"
                class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border bg-muted/60"
            >
                <component :is="icon" class="h-7 w-7" />
            </div>

            <div class="space-y-1">
                <h1 class="text-3xl font-bold tracking-tight">
                    {{ title }}
                </h1>

                <p v-if="description" class="text-muted-foreground">
                    {{ description }}
                </p>
            </div>
        </div>

        <!-- Right -->

        <div v-if="$slots.actions" class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
