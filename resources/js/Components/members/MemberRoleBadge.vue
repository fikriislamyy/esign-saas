<script setup>
import { computed } from "vue";

import { Crown, ShieldCheck, User } from "lucide-vue-next";

import { Badge } from "@/components/ui/badge";

const props = defineProps({
    role: {
        type: String,
        required: true,
    },
});

const config = computed(() => {
    switch (props.role) {
        case "owner":
            return {
                label: "Owner",
                variant: "default",
                icon: Crown,
            };

        case "admin":
            return {
                label: "Admin",
                variant: "secondary",
                icon: ShieldCheck,
            };

        default:
            return {
                label: "Member",
                variant: "outline",
                icon: User,
            };
    }
});
</script>

<template>
    <Badge :variant="config.variant" class="gap-1">
        <component :is="config.icon" class="h-3.5 w-3.5" />

        {{ config.label }}
    </Badge>
</template>
