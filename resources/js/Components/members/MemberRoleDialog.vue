<script setup>
import { ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { Shield, UserRound, Loader2, Save } from "lucide-vue-next";

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

import { Button } from "@/components/ui/button";

import LoadingOverlay from "@/Components/feedback/LoadingOverlay.vue";
import FeedbackDialog from "@/Components/feedback/FeedbackDialog.vue";

import { useFeedback } from "@/Composables/useFeedback";

const props = defineProps({
    member: {
        type: Object,
        required: true,
    },
});

const open = defineModel("open", {
    type: Boolean,
    default: false,
});

const emit = defineEmits(["updated"]);

const selectedRole = ref(props.member.role);

watch(
    () => props.member,
    (member) => {
        if (member) {
            selectedRole.value = member.role;
        }
    },
    { immediate: true },
);

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

function submit() {
    if (loading.value) {
        return;
    }

    // Nothing changed
    if (!selectedRole.value || selectedRole.value === props.member.role) {
        open.value = false;
        return;
    }

    showLoading("Updating member role...");

    router.patch(
        route("members.role.update", props.member.id),
        {
            role: selectedRole.value,
        },
        {
            preserveScroll: true,

            onSuccess: () => {
                // Close the role dialog immediately
                open.value = false;

                // Tell parent that the update succeeded
                emit("updated");

                // Show success feedback
                showSuccess(
                    `${props.member.name}'s role has been updated successfully.`,
                    "Role Updated",
                    "Continue",
                );
            },

            onError: (errors) => {
                const message =
                    errors?.role ||
                    errors?.message ||
                    "Failed to update the member role. Please try again.";

                showError(message, "Role Update Failed", "Close");
            },

            onFinish: () => {
                hideLoading();
            },
        },
    );
}

function handleFeedbackConfirm() {
    const shouldRefresh =
        feedbackType.value === "success" &&
        feedbackTitle.value === "Role Updated";

    closeFeedback();

    if (shouldRefresh) {
        router.reload({
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <!-- Loading -->
    <LoadingOverlay :show="loading" :text="loadingText" fullscreen />

    <!-- Feedback -->
    <FeedbackDialog
        v-model:open="feedbackOpen"
        :type="feedbackType"
        :title="feedbackTitle"
        :message="feedbackMessage"
        :button-text="feedbackButtonText"
        @close="closeFeedback"
        @confirm="handleFeedbackConfirm"
    />

    <!-- Role Dialog -->
    <Dialog v-model:open="open">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle> Change Member Role </DialogTitle>

                <DialogDescription>
                    Update the role assigned to this organization member.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-5 py-2">
                <!-- Member -->
                <div class="rounded-xl border bg-muted/30 p-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary"
                        >
                            <UserRound class="h-5 w-5" />
                        </div>

                        <div class="min-w-0">
                            <p class="truncate font-medium">
                                {{ member.name }}
                            </p>

                            <p class="truncate text-sm text-muted-foreground">
                                {{ member.email }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Role -->
                <div class="space-y-2">
                    <label class="text-sm font-medium"> Role </label>

                    <Select v-model="selectedRole">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Select role" />
                        </SelectTrigger>

                        <SelectContent>
                            <SelectItem value="member">
                                <div class="flex items-center gap-2">
                                    <UserRound class="h-4 w-4" />
                                    <span>Member</span>
                                </div>
                            </SelectItem>

                            <SelectItem value="admin">
                                <div class="flex items-center gap-2">
                                    <Shield class="h-4 w-4" />
                                    <span>Admin</span>
                                </div>
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <p class="text-xs text-muted-foreground">
                        Admins can manage organization members. Members have
                        standard workspace access.
                    </p>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="loading"
                    @click="open = false"
                >
                    Cancel
                </Button>

                <Button
                    type="button"
                    :disabled="
                        loading || !selectedRole || selectedRole === member.role
                    "
                    @click="submit"
                >
                    <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />

                    <Save v-else class="mr-2 h-4 w-4" />

                    Save Changes
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
