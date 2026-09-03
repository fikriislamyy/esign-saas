<script setup>
import {
    FlexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useVueTable,
} from "@tanstack/vue-table";

import { ref } from "vue";

import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";

import DataTablePagination from "./DataTablePagination.vue";
import DataTableToolbar from "./DataTableToolbar.vue";
import { valueUpdater } from "./utils";

const props = defineProps({
    columns: {
        type: Array,
        required: true,
    },

    data: {
        type: Array,
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

const sorting = ref([]);
const columnFilters = ref([]);
const columnVisibility = ref({});
const rowSelection = ref({});

const table = useVueTable({
    get data() {
        return props.data;
    },

    get columns() {
        return props.columns;
    },

    state: {
        get sorting() {
            return sorting.value;
        },

        get columnFilters() {
            return columnFilters.value;
        },

        get columnVisibility() {
            return columnVisibility.value;
        },

        get rowSelection() {
            return rowSelection.value;
        },
    },

    enableRowSelection: true,

    onSortingChange: (updaterOrValue) => valueUpdater(updaterOrValue, sorting),

    onColumnFiltersChange: (updaterOrValue) =>
        valueUpdater(updaterOrValue, columnFilters),

    onColumnVisibilityChange: (updaterOrValue) =>
        valueUpdater(updaterOrValue, columnVisibility),

    onRowSelectionChange: (updaterOrValue) =>
        valueUpdater(updaterOrValue, rowSelection),

    getCoreRowModel: getCoreRowModel(),

    getFilteredRowModel: getFilteredRowModel(),

    getSortedRowModel: getSortedRowModel(),

    getPaginationRowModel: getPaginationRowModel(),
});
</script>

<template>
    <div class="space-y-4">
        <DataTableToolbar
            :table="table"
            :search-column="searchColumn"
            :search-placeholder="searchPlaceholder"
        />
        <div class="rounded-xl border overflow-hidden">
            <Table>
                <TableHeader>
                    <TableRow
                        v-for="headerGroup in table.getHeaderGroups()"
                        :key="headerGroup.id"
                    >
                        <TableHead
                            v-for="header in headerGroup.headers"
                            :key="header.id"
                        >
                            <FlexRender
                                v-if="!header.isPlaceholder"
                                :render="header.column.columnDef.header"
                                :props="header.getContext()"
                            />
                        </TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <template v-if="table.getRowModel().rows?.length">
                        <TableRow
                            v-for="row in table.getRowModel().rows"
                            :key="row.id"
                            :data-state="
                                row.getIsSelected() ? 'selected' : undefined
                            "
                            class="hover:bg-muted/50 transition-colors"
                        >
                            <TableCell
                                v-for="cell in row.getVisibleCells()"
                                :key="cell.id"
                            >
                                <FlexRender
                                    :render="cell.column.columnDef.cell"
                                    :props="cell.getContext()"
                                />
                            </TableCell>
                        </TableRow>
                    </template>

                    <TableRow v-else>
                        <TableCell
                            :colspan="columns.length"
                            class="h-40 text-center text-muted-foreground"
                        >
                            No results found.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <DataTablePagination :table="table" />
    </div>
</template>
