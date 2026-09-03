<script setup>
import { router } from "@inertiajs/vue3";

import { Mail, Copy, RefreshCcw, Trash2, Clock3 } from "lucide-vue-next";

import { Button } from "@/components/ui/button";

import MemberRoleBadge from "./MemberRoleBadge.vue";

const props = defineProps({
    invitations: {
        type: Array,
        default: () => [],
    },
});

function revokeInvitation(invitation) {
    if (!confirm(`Revoke invitation for ${invitation.email}?`)) {
        return;
    }

    router.delete(route("invitations.destroy", invitation.id));
}

function resendInvitation(invitation) {
    router.post(route("invitations.resend", invitation.id));
}

async function copyInvitationLink(invitation) {
    const url = `${window.location.origin}/invitations/${invitation.token}`;

    await navigator.clipboard.writeText(url);

    alert("Invitation link copied.");
}

function formatDate(date) {
    return new Date(date).toLocaleString();
}

const emit = defineEmits(["copy", "resend", "revoke"]);
</script>

<template>
    <div class="space-y-4">
        <!-- Empty -->

        <div
            v-if="!invitations.length"
            class="flex flex-col items-center justify-center rounded-xl border border-dashed py-14 text-center"
        >
            <Mail class="mb-4 h-10 w-10 text-muted-foreground" />

            <h3 class="font-semibold">No Pending Invitations</h3>

            <p class="mt-2 max-w-sm text-sm text-muted-foreground">
                Everyone you've invited has already joined your organization.
            </p>
        </div>

        <!-- Cards -->

        <div v-else class="space-y-4">
            <div
                v-for="invitation in invitations"
                :key="invitation.id"
                class="rounded-xl border bg-card p-5 transition hover:shadow-sm"
            >
                <div
                    class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between"
                >
                    <!-- Left -->

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-full bg-primary/10"
                            >
                                <Mail class="h-5 w-5 text-primary" />
                            </div>

                            <div class="min-w-0">
                                <p class="truncate font-semibold">
                                    {{ invitation.email }}
                                </p>

                                <div
                                    class="mt-2 flex flex-wrap items-center gap-2"
                                >
                                    <MemberRoleBadge :role="invitation.role" />

                                    <span
                                        class="flex items-center gap-1 text-xs text-muted-foreground"
                                    >
                                        <Clock3 class="h-3.5 w-3.5" />

                                        {{ formatDate(invitation.created_at) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->

                    <div class="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            @click="$emit('copy', invitation)"
                        >
                            <Copy class="mr-2 h-4 w-4" />

                            Copy Link
                        </Button>

                        <Button
                            variant="outline"
                            @click="$emit('resend', invitation)"
                        >
                            <RefreshCcw class="mr-2 h-4 w-4" />

                            Resend
                        </Button>

                        <Button
                            variant="destructive"
                            @click="$emit('revoke', invitation)"
                        >
                            <Trash2 class="mr-2 h-4 w-4" />

                            Revoke
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
