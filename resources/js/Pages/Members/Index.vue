<script setup>
import { ref } from "vue";
import { Head, useForm, router } from "@inertiajs/vue3";

import { Users } from "lucide-vue-next";

import AppLayout from "@/Layouts/AppLayout.vue";

import FadeIn from "@/Components/animations/FadeIn.vue";

import PageHeader from "@/Components/page/PageHeader.vue";
import PageSection from "@/Components/page/PageSection.vue";

import LoadingOverlay from "@/Components/feedback/LoadingOverlay.vue";
import FeedbackDialog from "@/Components/feedback/FeedbackDialog.vue";

import { useFeedback } from "@/Composables/useFeedback";

import InviteMemberCard from "@/Components/members/InviteMemberCard.vue";
import MembersTable from "@/Components/members/MembersTable.vue";
import PendingInvitations from "@/Components/members/PendingInvitations.vue";
import MemberRoleDialog from "@/Components/members/MemberRoleDialog.vue";

const props = defineProps({
    members: {
        type: Array,
        default: () => [],
    },

    invitations: {
        type: Array,
        default: () => [],
    },

    canManageMembers: {
        type: Boolean,
        default: false,
    },
});

const inviteForm = useForm({
    email: "",
    role: "member",
});

/*
|--------------------------------------------------------------------------
| Role change
|--------------------------------------------------------------------------
*/

const selectedMember = ref(null);
const roleDialogOpen = ref(false);

function openRoleDialog(member) {
    selectedMember.value = member;
    roleDialogOpen.value = true;
}

/*
|--------------------------------------------------------------------------
| Feedback / Loading
|--------------------------------------------------------------------------
*/

const {
    loading,
    loadingText,
    feedbackOpen,
    feedbackType,
    feedbackTitle,
    feedbackMessage,
    feedbackButtonText,

    showLoading,
    hideLoading,
    showSuccess,
    showError,
    closeFeedback,
} = useFeedback();

/*
|--------------------------------------------------------------------------
| Feedback handling
|--------------------------------------------------------------------------
*/

function handleFeedbackClose() {
    const refreshTitles = [
        "Invitation Sent",
        "Invitation Revoked",
        "Invitation Resent",
        "Role Updated",
    ];

    const shouldRefresh =
        feedbackType.value === "success" &&
        refreshTitles.includes(feedbackTitle.value);

    closeFeedback();

    if (shouldRefresh) {
        router.reload({
            preserveScroll: true,
        });
    }
}

/*
|--------------------------------------------------------------------------
| Invitations
|--------------------------------------------------------------------------
*/

function submitInvitation() {
    if (inviteForm.processing) {
        return;
    }

    showLoading("Sending invitation...");

    inviteForm.post(route("invitations.store"), {
        preserveScroll: true,

        onSuccess: () => {
            inviteForm.reset("email");

            showSuccess(
                "The invitation has been sent successfully.",
                "Invitation Sent",
                "Continue",
            );
        },

        onError: (errors) => {
            const message =
                errors?.email ||
                errors?.role ||
                errors?.invitation ||
                errors?.message ||
                "Failed to send the invitation. Please try again.";

            showError(message, "Invitation Failed", "Close");
        },

        onFinish: () => {
            hideLoading();
        },
    });
}

function revokeInvitation(invitation) {
    showLoading("Revoking invitation...");

    router.delete(route("invitations.destroy", invitation.id), {
        preserveScroll: true,

        onSuccess: () => {
            showSuccess(
                "The invitation has been revoked successfully.",
                "Invitation Revoked",
                "Continue",
            );
        },

        onError: (errors) => {
            const message =
                errors?.invitation ||
                errors?.message ||
                "Failed to revoke the invitation. Please try again.";

            showError(message, "Revoke Failed", "Close");
        },

        onFinish: () => {
            hideLoading();
        },
    });
}

function resendInvitation(invitation) {
    showLoading("Resending invitation...");

    router.post(
        route("invitations.resend", invitation.id),
        {},
        {
            preserveScroll: true,

            onSuccess: () => {
                showSuccess(
                    `The invitation for ${invitation.email} has been resent successfully.`,
                    "Invitation Resent",
                    "Continue",
                );
            },

            onError: (errors) => {
                const message =
                    errors?.email ||
                    errors?.invitation ||
                    errors?.message ||
                    "Failed to resend the invitation. Please try again.";

                showError(message, "Resend Failed", "Close");
            },

            onFinish: () => {
                hideLoading();
            },
        },
    );
}

async function copyInvitation(invitation) {
    await navigator.clipboard.writeText(
        `${window.location.origin}/invitations/${invitation.token}`,
    );
}
</script>

<template>
    <Head title="Members" />

    <AppLayout>
        <!-- Loading -->
        <LoadingOverlay :show="loading" :text="loadingText" fullscreen />

        <!-- Feedback -->
        <FeedbackDialog
            v-model:open="feedbackOpen"
            :type="feedbackType"
            :title="feedbackTitle"
            :message="feedbackMessage"
            :button-text="feedbackButtonText"
            @close="handleFeedbackClose"
        />

        <!-- Role Dialog -->
        <MemberRoleDialog
            v-if="selectedMember"
            v-model:open="roleDialogOpen"
            :member="selectedMember"
        />

        <div class="space-y-8">
            <!-- Header -->
            <FadeIn :delay="100" type="fade">
                <PageHeader
                    title="Members"
                    description="Manage your organization's members, roles, and invitations."
                    :icon="Users"
                />
            </FadeIn>

            <!-- Invite -->
            <FadeIn v-if="canManageMembers" :delay="200" type="scale">
                <PageSection
                    :padding="true"
                    title="Invite Member"
                    description="Invite people to collaborate in your organization."
                >
                    <InviteMemberCard
                        :form="inviteForm"
                        @submit="submitInvitation"
                    />
                </PageSection>
            </FadeIn>

            <!-- Members -->
            <FadeIn :delay="300" type="scale">
                <PageSection
                    title="Team Members"
                    description="Browse, search, and manage organization members."
                    :padding="false"
                >
                    <MembersTable
                        :members="members"
                        :can-manage-members="canManageMembers"
                        @role-change="openRoleDialog"
                    />
                </PageSection>
            </FadeIn>

            <!-- Invitations -->
            <FadeIn
                v-if="canManageMembers && invitations.length"
                :delay="400"
                type="scale"
            >
                <PageSection
                    title="Pending Invitations"
                    description="Invitations waiting to be accepted."
                >
                    <PendingInvitations
                        :invitations="invitations"
                        @copy="copyInvitation"
                        @resend="resendInvitation"
                        @revoke="revokeInvitation"
                    />
                </PageSection>
            </FadeIn>
        </div>
    </AppLayout>
</template>
