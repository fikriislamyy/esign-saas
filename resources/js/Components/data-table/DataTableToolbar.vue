<script setup>
import { X, Search } from "lucide-vue-next";
import { computed } from "vue";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

import DataTableViewOptions from "./DataTableViewOptions.vue";

const props = defineProps({
    table: {
        type: Object,
        required: true,
    },

    searchColumn: {
        type: String,
        default: "name",
    },

    searchPlaceholder: {
        type: String,
        default: "Search...",
    },
});

const hasFilters = computed(() => {
    return props.table.getState().columnFilters.length > 0;
});
</script>

<template>
    <div
        class="flex flex-col gap-4 py-2 md:flex-row md:items-center md:justify-between"
    >
        <!-- Left -->

        <div class="flex flex-1 items-center gap-3">
            <div class="relative w-full max-w-sm">
                <Search
                    class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                />

                <Input
                    class="pl-9"
                    :placeholder="searchPlaceholder"
                    :model-value="
                        table.getColumn(searchColumn)?.getFilterValue()
                    "
                    @update:model-value="
                        table.getColumn(searchColumn)?.setFilterValue($event)
                    "
                />
            </div>

            <Button
                v-if="hasFilters"
                variant="ghost"
                size="sm"
                @click="table.resetColumnFilters()"
            >
                Reset

                <X class="ml-2 h-4 w-4" />
            </Button>
        </div>

        <!-- Right -->

        <div class="flex items-center gap-3">
            <span class="text-sm text-muted-foreground">
                {{ table.getFilteredRowModel().rows.length }}
                results
            </span>

            <DataTableViewOptions :table="table" />
        </div>
    </div>
</template>
