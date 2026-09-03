<script setup>
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from "lucide-vue-next";

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

import { Button } from "@/components/ui/button";

const props = defineProps({
    table: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div
        class="flex flex-col gap-4 border-t pt-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <!-- Left -->

        <div class="text-sm text-muted-foreground">
            Showing

            <span class="font-medium text-foreground">
                {{ table.getFilteredRowModel().rows.length }}
            </span>

            of

            <span class="font-medium text-foreground">
                {{ table.getCoreRowModel().rows.length }}
            </span>

            document(s)
        </div>

        <!-- Right -->

        <div class="flex items-center gap-6">
            <!-- Rows per page -->

            <div class="flex items-center gap-2">
                <span class="text-sm text-muted-foreground"> Rows </span>

                <Select
                    :model-value="String(table.getState().pagination.pageSize)"
                    @update:model-value="table.setPageSize(Number($event))"
                >
                    <SelectTrigger class="w-20">
                        <SelectValue />
                    </SelectTrigger>

                    <SelectContent>
                        <SelectItem value="10"> 10 </SelectItem>

                        <SelectItem value="20"> 20 </SelectItem>

                        <SelectItem value="30"> 30 </SelectItem>

                        <SelectItem value="50"> 50 </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Page -->

            <div class="text-sm font-medium">
                Page

                {{ table.getState().pagination.pageIndex + 1 }}

                of

                {{ table.getPageCount() }}
            </div>

            <!-- Buttons -->

            <div class="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="icon"
                    :disabled="!table.getCanPreviousPage()"
                    @click="table.setPageIndex(0)"
                >
                    <ChevronsLeft class="h-4 w-4" />
                </Button>

                <Button
                    variant="outline"
                    size="icon"
                    :disabled="!table.getCanPreviousPage()"
                    @click="table.previousPage()"
                >
                    <ChevronLeft class="h-4 w-4" />
                </Button>

                <Button
                    variant="outline"
                    size="icon"
                    :disabled="!table.getCanNextPage()"
                    @click="table.nextPage()"
                >
                    <ChevronRight class="h-4 w-4" />
                </Button>

                <Button
                    variant="outline"
                    size="icon"
                    :disabled="!table.getCanNextPage()"
                    @click="table.setPageIndex(table.getPageCount() - 1)"
                >
                    <ChevronsRight class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
