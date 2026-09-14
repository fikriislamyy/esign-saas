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
    <div class="border-t pt-4">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <!-- Result count -->

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

            <!-- Controls -->

            <div
                class="flex w-full items-center justify-between gap-3 sm:w-auto sm:justify-end sm:gap-6"
            >
                <!-- Rows per page -->

                <div class="flex shrink-0 items-center gap-2">
                    <span class="text-sm text-muted-foreground"> Rows </span>

                    <Select
                        :model-value="
                            String(table.getState().pagination.pageSize)
                        "
                        @update:model-value="table.setPageSize(Number($event))"
                    >
                        <SelectTrigger class="h-9 w-[72px]">
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

                <div class="shrink-0 whitespace-nowrap text-sm font-medium">
                    Page
                    {{ table.getState().pagination.pageIndex + 1 }}
                    of
                    {{ table.getPageCount() }}
                </div>

                <!-- Navigation -->

                <div class="flex shrink-0 items-center gap-1">
                    <!-- First -->
                    <Button
                        variant="outline"
                        size="icon"
                        class="hidden h-9 w-9 sm:inline-flex"
                        :disabled="!table.getCanPreviousPage()"
                        @click="table.setPageIndex(0)"
                    >
                        <ChevronsLeft class="h-4 w-4" />
                    </Button>

                    <!-- Previous -->
                    <Button
                        variant="outline"
                        size="icon"
                        class="h-9 w-9"
                        :disabled="!table.getCanPreviousPage()"
                        @click="table.previousPage()"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </Button>

                    <!-- Next -->
                    <Button
                        variant="outline"
                        size="icon"
                        class="h-9 w-9"
                        :disabled="!table.getCanNextPage()"
                        @click="table.nextPage()"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </Button>

                    <!-- Last -->
                    <Button
                        variant="outline"
                        size="icon"
                        class="hidden h-9 w-9 sm:inline-flex"
                        :disabled="!table.getCanNextPage()"
                        @click="table.setPageIndex(table.getPageCount() - 1)"
                    >
                        <ChevronsRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
