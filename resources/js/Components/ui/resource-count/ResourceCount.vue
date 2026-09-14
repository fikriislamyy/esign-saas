<script setup>
const props = defineProps({
    count: {
        type: Number,
        required: true,
    },

    singular: {
        type: String,
        required: true,
    },

    plural: {
        type: String,
        default: "",
    },

    showLabel: {
        type: Boolean,
        default: true,
    },
});

function label() {
    if (!props.showLabel) {
        return "";
    }

    if (props.count === 1) {
        return props.singular;
    }

    return props.plural || `${props.singular}s`;
}
</script>

<template>
    <div
        class="inline-flex h-8 items-center rounded-full border bg-muted/40 px-3 text-sm font-medium text-muted-foreground"
    >
        <span class="font-semibold text-foreground">
            {{ count }}
        </span>

        <span v-if="showLabel" class="ml-1">
            {{ label() }}
        </span>
    </div>
</template>
