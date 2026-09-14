<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";

import DataTable from "@/Components/data-table/DataTable.vue";
import ResourceCount from "@/Components/ui/resource-count/ResourceCount.vue";

import { createColumns } from "./columns";

const props = defineProps({
    members: {
        type: Array,
        default: () => [],
    },

    canManageMembers: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["role-change"]);

const page = usePage();

const currentUserId = computed(() => {
    return page.props.auth?.user?.id ?? null;
});

const columns = computed(() => {
    return createColumns({
        canManageMembers: props.canManageMembers,
        currentUserId: currentUserId.value,

        onRoleChange: (member) => {
            emit("role-change", member);
        },
    });
});
</script>

<template>
    <div class="space-y-6 p-6">
        <ResourceCount :count="members.length" singular="Member" />

        <DataTable
            :columns="columns"
            :data="members"
            search-column="name"
            search-placeholder="Search members..."
        />
    </div>
</template>
