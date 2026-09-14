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
    <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
        <!-- Left -->

        <div class="flex min-w-0 items-start gap-4">
            <div
                v-if="icon"
                class="hidden h-14 w-14 shrink-0 items-center justify-center rounded-2xl border bg-muted/60 sm:flex"
            >
                <component :is="icon" class="h-7 w-7" />
            </div>

            <div class="min-w-0 space-y-1">
                <h1 class="break-words text-2xl font-bold tracking-tight sm:text-3xl">
                    {{ title }}
                </h1>

                <p v-if="description" class="break-words text-muted-foreground">
                    {{ description }}
                </p>
            </div>
        </div>

        <!-- Right -->

        <div
            v-if="$slots.actions"
            class="grid gap-2 sm:flex sm:flex-wrap sm:items-center"
        >
            <slot name="actions" />
        </div>
    </div>
</template>
