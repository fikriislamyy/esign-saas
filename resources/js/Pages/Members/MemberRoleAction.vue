<script setup>
import { ref } from "vue";
import { MoreHorizontal, Pencil } from "lucide-vue-next";

import { Button } from "@/components/ui/button";

import MemberRoleDialog from "@/Components/members/MemberRoleDialog.vue";

const props = defineProps({
    member: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(["change"]);

const open = ref(false);

function handleUpdated() {
    emit("change");
}
</script>

<template>
    <div>
        <Button
            variant="ghost"
            size="icon"
            class="h-8 w-8"
            title="Change role"
            @click="open = true"
        >
            <Pencil class="h-4 w-4" />
        </Button>

        <MemberRoleDialog
            v-model:open="open"
            :member="member"
            @updated="handleUpdated"
        />
    </div>
</template>
