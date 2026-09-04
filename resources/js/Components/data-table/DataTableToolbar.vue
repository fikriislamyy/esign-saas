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

const searchValue = computed(() => {
    return props.table.getColumn(props.searchColumn)?.getFilterValue() ?? "";
});

function clearSearch() {
    props.table.getColumn(props.searchColumn)?.setFilterValue("");
}

function resetFilters() {
    props.table.resetColumnFilters();
}
</script>

<template>
    <div
        class="flex flex-col gap-3 py-2 sm:gap-4 md:flex-row md:items-center md:justify-between"
    >
        <!-- Search -->
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <div class="relative w-full md:max-w-sm">
                <Search
                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                />

                <Input
                    class="h-10 w-full pl-9 pr-9"
                    :placeholder="searchPlaceholder"
                    :model-value="searchValue"
                    @update:model-value="
                        table.getColumn(searchColumn)?.setFilterValue($event)
                    "
                />

                <!-- Clear search -->
                <button
                    v-if="searchValue"
                    type="button"
                    class="absolute right-2 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    aria-label="Clear search"
                    @click="clearSearch"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Reset -->
            <Button
                v-if="hasFilters"
                variant="ghost"
                size="sm"
                class="h-10 shrink-0"
                @click="resetFilters"
            >
                <span class="hidden sm:inline">Reset</span>

                <X class="h-4 w-4 sm:ml-2" />
            </Button>
        </div>

        <!-- Right -->
        <div class="flex items-center justify-between gap-3 md:justify-end">
            <!-- Result count -->
            <span class="text-sm text-muted-foreground">
                {{ table.getFilteredRowModel().rows.length }}
                results
            </span>

            <!-- View options -->
            <DataTableViewOptions :table="table" />
        </div>
    </div>
</template>
