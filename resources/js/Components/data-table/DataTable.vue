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
        <!-- Toolbar -->
        <DataTableToolbar
            :table="table"
            :search-column="searchColumn"
            :search-placeholder="searchPlaceholder"
        />

        <!-- ========================================= -->
        <!-- DESKTOP TABLE -->
        <!-- ========================================= -->
        <div class="hidden md:block rounded-xl border overflow-hidden">
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

        <!-- ========================================= -->
        <!-- MOBILE CARDS -->
        <!-- ========================================= -->
        <div class="md:hidden space-y-3">
            <template v-if="table.getRowModel().rows?.length">
                <div
                    v-for="row in table.getRowModel().rows"
                    :key="row.id"
                    class="rounded-xl border bg-background p-4 shadow-sm"
                >
                    <div
                        v-for="cell in row.getVisibleCells()"
                        :key="cell.id"
                        class="flex items-start justify-between gap-4 py-2"
                        :class="{
                            'border-b pb-3 mb-1': cell.column.id === 'name',

                            'pt-3': cell.column.id === 'actions',
                        }"
                    >
                        <!-- Label -->
                        <div
                            class="shrink-0 text-sm text-muted-foreground"
                            :class="{
                                'sr-only': cell.column.id === 'name',
                            }"
                        >
                            {{
                                cell.column.columnDef.meta?.label ??
                                cell.column.id
                            }}
                        </div>

                        <!-- Value -->
                        <div
                            class="min-w-0 text-right"
                            :class="{
                                'w-full text-left': cell.column.id === 'name',

                                'flex-1':
                                    cell.column.id !== 'name' &&
                                    cell.column.id !== 'actions',

                                'ml-auto': cell.column.id === 'actions',
                            }"
                        >
                            <FlexRender
                                :render="cell.column.columnDef.cell"
                                :props="cell.getContext()"
                            />
                        </div>
                    </div>
                </div>
            </template>

            <!-- Empty state -->
            <div
                v-else
                class="rounded-xl border px-4 py-12 text-center text-sm text-muted-foreground"
            >
                No results found.
            </div>
        </div>

        <!-- Pagination -->
        <DataTablePagination :table="table" />
    </div>
</template>
