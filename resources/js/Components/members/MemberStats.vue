<script setup>
import StatCard from "@/Components/common/StatCard.vue";

import { Users, ShieldCheck, User, MailPlus } from "lucide-vue-next";

const props = defineProps({
    members: {
        type: Array,
        default: () => [],
    },

    invitations: {
        type: Array,
        default: () => [],
    },
});

const ownerCount = props.members.filter(
    (member) => member.role === "owner",
).length;

const adminCount = props.members.filter(
    (member) => member.role === "admin",
).length;

const memberCount = props.members.filter(
    (member) => member.role === "member",
).length;
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard
            title="Total Members"
            :value="members.length"
            description="Organization users"
            :icon="Users"
        />

        <StatCard
            title="Owners & Admins"
            :value="ownerCount + adminCount"
            description="Management access"
            :icon="ShieldCheck"
        />

        <StatCard
            title="Members"
            :value="memberCount"
            description="Regular members"
            :icon="User"
        />

        <StatCard
            title="Pending Invites"
            :value="invitations.length"
            description="Awaiting acceptance"
            :icon="MailPlus"
        />
    </div>
</template>
