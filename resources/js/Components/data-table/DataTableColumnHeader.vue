<script setup>
import { ArrowUp, ArrowDown, ChevronsUpDown } from "lucide-vue-next";

import { Button } from "@/components/ui/button";

const props = defineProps({
    column: {
        type: Object,
        required: true,
    },

    title: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Button
        variant="ghost"
        class="h-8 px-2 hover:bg-transparent"
        @click="column.toggleSorting(column.getIsSorted() === 'asc')"
    >
        <span>
            {{ title }}
        </span>

        <ChevronsUpDown v-if="!column.getIsSorted()" class="ml-2 h-4 w-4" />

        <ArrowUp
            v-else-if="column.getIsSorted() === 'asc'"
            class="ml-2 h-4 w-4"
        />

        <ArrowDown v-else class="ml-2 h-4 w-4" />
    </Button>
</template>
