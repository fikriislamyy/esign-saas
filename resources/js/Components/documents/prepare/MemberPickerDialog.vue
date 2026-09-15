<script setup>
import { computed, ref, watch } from "vue";
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
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";

const props = defineProps({
    open: { type: Boolean, default: false },
    members: { type: Array, default: () => [] },
    signers: { type: Array, default: () => [] },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(["update:open", "select"]);

const search = ref("");
const sequential = ref(false);

watch(
    () => props.open,
    (open) => {
        if (open) {
            search.value = "";
        }
    },
);

const hasSigners = computed(() => props.signers.length > 0);

const workflowIsSequential = computed(() =>
    hasSigners.value ? props.signers[0].signing_order > 0 : sequential.value,
);

const signerEmails = computed(() => new Set(props.signers.map((s) => s.email)));

const filteredMembers = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) {
        return props.members;
    }

    return props.members.filter(
        (member) =>
            member.name.toLowerCase().includes(term) ||
            member.email.toLowerCase().includes(term),
    );
});

function choose(member) {
    emit("select", member, workflowIsSequential.value);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Choose Signer</DialogTitle>

                <DialogDescription>
                    Pick the member who must sign this field.
                </DialogDescription>
            </DialogHeader>

            <!-- Workflow: only editable before the first signer exists -->

            <div v-if="!hasSigners" class="flex items-center gap-3 rounded-xl border p-3">
                <Checkbox v-model="sequential" />

                <div>
                    <p class="text-sm font-medium">Sequential Signing</p>
                    <p class="text-xs text-muted-foreground">Signers must sign in order.</p>
                </div>
            </div>

            <p v-else class="rounded-xl bg-muted/50 p-3 text-xs text-muted-foreground">
                Workflow: {{ workflowIsSequential ? "Sequential" : "Parallel" }} signing (locked
                because signers already exist).
            </p>

            <div class="space-y-2">
                <Label>Search Member</Label>
                <Input v-model="search" placeholder="Search by name or email..." />
            </div>

            <div class="max-h-[40vh] space-y-2 overflow-y-auto">
                <button
                    v-for="member in filteredMembers"
                    :key="member.id"
                    type="button"
                    :disabled="processing"
                    class="flex w-full items-center gap-3 rounded-xl border p-3 text-left transition-colors hover:bg-muted/60 disabled:opacity-50"
                    @click="choose(member)"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <UserRound class="h-5 w-5" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ member.name }}</p>
                        <p class="truncate text-xs text-muted-foreground">{{ member.email }}</p>
                    </div>

                    <span
                        v-if="signerEmails.has(member.email)"
                        class="shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                    >
                        Signer
                    </span>

                    <Check v-else class="h-4 w-4 shrink-0 text-muted-foreground" />
                </button>

                <p v-if="!filteredMembers.length" class="p-4 text-sm text-muted-foreground">
                    No members found.
                </p>
            </div>

            <DialogFooter class="grid gap-2 sm:flex sm:justify-end">
                <Button variant="outline" :disabled="processing" @click="emit('update:open', false)">
                    Cancel
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
