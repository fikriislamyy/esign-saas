<script setup>
import { computed } from "vue";

import { CheckCircle2, CircleAlert, CircleHelp } from "lucide-vue-next";

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";

import { Button } from "@/components/ui/button";

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },

    type: {
        type: String,
        default: "success",

        validator: (value) =>
            ["success", "error", "confirmation"].includes(value),
    },

    title: {
        type: String,
        default: "",
    },

    message: {
        type: String,
        default: "",
    },

    buttonText: {
        type: String,
        default: "Continue",
    },

    cancelText: {
        type: String,
        default: "Cancel",
    },
});

const emit = defineEmits(["update:open", "close", "confirm"]);

const isSuccess = computed(() => props.type === "success");

const isError = computed(() => props.type === "error");

const isConfirmation = computed(() => props.type === "confirmation");

const icon = computed(() => {
    if (isConfirmation.value) {
        return CircleHelp;
    }

    if (isError.value) {
        return CircleAlert;
    }

    return CheckCircle2;
});

const defaultTitle = computed(() => {
    if (isConfirmation.value) {
        return "Are you sure?";
    }

    if (isError.value) {
        return "Something went wrong";
    }

    return "Success";
});

const defaultMessage = computed(() => {
    if (isConfirmation.value) {
        return "Please confirm this action.";
    }

    if (isError.value) {
        return "The operation could not be completed.";
    }

    return "The operation was completed successfully.";
});

function close() {
    emit("update:open", false);
    emit("close");
}

function confirm() {
    emit("confirm");
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <div class="flex flex-col items-center text-center">
                    <!-- Icon -->

                    <div
                        :class="[
                            'mb-4 flex h-14 w-14 items-center justify-center rounded-full',
                            isSuccess &&
                                'bg-green-100 text-green-600 dark:bg-green-950 dark:text-green-400',
                            isError && 'bg-destructive/10 text-destructive',
                            isConfirmation && 'bg-primary/10 text-primary',
                        ]"
                    >
                        <component :is="icon" class="h-7 w-7" />
                    </div>

                    <!-- Title -->

                    <DialogTitle class="text-xl">
                        {{ title || defaultTitle }}
                    </DialogTitle>

                    <!-- Description -->

                    <DialogDescription
                        class="mt-2 text-center text-sm leading-6 text-muted-foreground"
                    >
                        {{ message || defaultMessage }}
                    </DialogDescription>
                </div>
            </DialogHeader>

            <!-- Confirmation -->

            <DialogFooter
                v-if="isConfirmation"
                class="grid grid-cols-2 gap-3 sm:flex sm:justify-center"
            >
                <Button
                    variant="outline"
                    class="w-full sm:w-auto"
                    @click="close"
                >
                    {{ cancelText }}
                </Button>

                <Button class="w-full sm:w-auto" @click="confirm">
                    {{ buttonText }}
                </Button>
            </DialogFooter>

            <!-- Success / Error -->

            <DialogFooter v-else class="sm:justify-center">
                <Button class="w-full sm:w-auto" @click="close">
                    {{ buttonText }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
