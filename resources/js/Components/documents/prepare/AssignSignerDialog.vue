<script setup>
import { Check, UserRound } from "lucide-vue-next";

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";

import { Button } from "@/components/ui/button";

defineProps({
    open: { type: Boolean, default: false },
    signers: { type: Array, default: () => [] },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(["update:open", "assign"]);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Assign Signer</DialogTitle>

                <DialogDescription>
                    Choose who must sign in this field.
                </DialogDescription>
            </DialogHeader>

            <div class="max-h-[50vh] space-y-2 overflow-y-auto">
                <button
                    v-for="signer in signers"
                    :key="signer.id"
                    type="button"
                    :disabled="processing"
                    class="flex w-full items-center gap-3 rounded-xl border p-3 text-left transition-colors hover:bg-muted/60 disabled:opacity-50"
                    @click="emit('assign', signer)"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <UserRound class="h-5 w-5" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ signer.name }}</p>
                        <p class="truncate text-xs text-muted-foreground">{{ signer.email }}</p>
                    </div>

                    <Check class="h-4 w-4 shrink-0 text-muted-foreground" />
                </button>
            </div>

            <DialogFooter class="grid gap-2 sm:flex sm:justify-end">
                <Button variant="outline" :disabled="processing" @click="emit('update:open', false)">
                    Cancel
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
