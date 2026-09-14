<script setup>
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

import { Button } from "@/components/ui/button";

import { SlidersHorizontal } from "lucide-vue-next";

const props = defineProps({
    table: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="outline"
                size="sm"
                class="ml-auto hidden h-9 lg:flex"
            >
                <SlidersHorizontal class="mr-2 h-4 w-4" />

                View
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-48">
            <DropdownMenuLabel> Toggle Columns </DropdownMenuLabel>

            <DropdownMenuSeparator />

            <DropdownMenuCheckboxItem
                v-for="column in table
                    .getAllColumns()
                    .filter(
                        (column) =>
                            column.getCanHide() && column.columnDef.accessorKey,
                    )"
                :key="column.id"
                :model-value="column.getIsVisible()"
                @update:model-value="
                    (value) => column.toggleVisibility(!!value)
                "
                class="capitalize"
            >
                {{ column.columnDef.meta?.label }}
            </DropdownMenuCheckboxItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
