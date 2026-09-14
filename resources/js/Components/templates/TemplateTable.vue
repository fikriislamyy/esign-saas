<script setup>
import DataTable from "@/Components/data-table/DataTable.vue";

import { columns } from "./columns";
import { ResourceCount } from "@/Components/ui/resource-count";
defineProps({
    templates: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div class="space-y-4 px-6 pb-6">
        <div class="flex items-center justify-between px-6 pt-6">
            <ResourceCount :count="templates.length" singular="Template" />
        </div>

        <DataTable
            :columns="columns"
            :data="templates"
            search-column="name"
            search-placeholder="Search templates..."
        />
    </div>
</template>
