<script setup>
import DataTable from "@/Components/data-table/DataTable.vue";

import { columns } from "./columns";
import { ResourceCount } from "@/Components/ui/resource-count";
defineProps({
    documents: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div class="space-y-4 px-6 pb-6">
        <div class="flex items-center justify-between px-6 pt-6">
            <ResourceCount :count="documents.length" singular="Document" />
        </div>

        <DataTable
            :columns="columns"
            :data="documents"
            search-column="name"
            search-placeholder="Search documents..."
        />
    </div>
</template>
