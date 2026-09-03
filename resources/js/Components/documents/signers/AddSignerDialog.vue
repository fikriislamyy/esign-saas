<script setup>
import { computed, ref } from "vue";
import { useForm } from "@inertiajs/vue3";

import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";

const props = defineProps({
    document: {
        type: Object,
        required: true,
    },

    members: {
        type: Array,
        required: true,
    },
});

const open = ref(false);

const search = ref("");

const isSequential = ref(false);

const form = useForm({
    member_id: "",
    signing_order: 0,
});

const hasSigners = computed(() => {
    return props.document.signers.length > 0;
});

const workflowIsSequential = computed(() => {
    if (!props.document.signers.length) {
        return isSequential.value;
    }

    return props.document.signers[0].signing_order > 0;
});

const nextOrder = computed(() => {
    if (!props.document.signers.length) {
        return 1;
    }

    const orders = props.document.signers
        .map((s) => s.signing_order)
        .filter((o) => o > 0);

    return orders.length ? Math.max(...orders) + 1 : 1;
});

const filteredMembers = computed(() => {
    if (!search.value) {
        return props.members;
    }

    return props.members.filter(
        (member) =>
            member.name.toLowerCase().includes(search.value.toLowerCase()) ||
            member.email.toLowerCase().includes(search.value.toLowerCase()),
    );
});

const selectedMember = computed(() => {
    return props.members.find((member) => member.id === form.member_id);
});

function submit() {
    form.signing_order = workflowIsSequential.value ? nextOrder.value : 0;

    form.post(route("documents.signers.store", props.document.id), {
        preserveScroll: true,

        onSuccess: () => {
            form.reset();

            search.value = "";

            open.value = false;

            isSequential.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button> Add Signer </Button>
        </DialogTrigger>

        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle> Add Signer </DialogTitle>
            </DialogHeader>

            <form class="space-y-5" @submit.prevent="submit">
                <!-- Workflow -->

                <div v-if="!hasSigners" class="space-y-3 rounded-xl border p-4">
                    <Label> Signing Workflow </Label>

                    <div class="flex items-center gap-3">
                        <Checkbox v-model="isSequential" />

                        <div>
                            <p class="font-medium text-sm">
                                Sequential Signing
                            </p>

                            <p class="text-xs text-muted-foreground">
                                Signers must sign in order.
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="!isSequential"
                        class="rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground"
                    >
                        Parallel signing enabled.
                    </div>
                </div>

                <div v-else class="rounded-xl border p-4">
                    <p class="font-medium">Signing Workflow</p>

                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            workflowIsSequential
                                ? "Sequential Signing"
                                : "Parallel Signing"
                        }}
                    </p>

                    <p class="mt-2 text-xs text-muted-foreground">
                        Workflow is locked because signers already exist.
                    </p>
                </div>

                <!-- Search -->

                <div class="space-y-2">
                    <Label> Search Member </Label>

                    <Input v-model="search" placeholder="Search member..." />
                </div>

                <!-- Members -->

                <div class="max-h-60 overflow-y-auto rounded-xl border">
                    <button
                        v-for="member in filteredMembers"
                        :key="member.id"
                        type="button"
                        class="flex w-full items-center justify-between border-b p-3 text-left transition hover:bg-muted"
                        @click="form.member_id = member.id"
                    >
                        <div>
                            <p class="font-medium">
                                {{ member.name }}
                            </p>

                            <p class="text-xs text-muted-foreground">
                                {{ member.email }}
                            </p>
                        </div>

                        <div
                            v-if="form.member_id === member.id"
                            class="text-sm text-primary"
                        >
                            Selected
                        </div>
                    </button>

                    <div
                        v-if="!filteredMembers.length"
                        class="p-4 text-sm text-muted-foreground"
                    >
                        No members found.
                    </div>
                </div>

                <!-- Selected -->

                <div
                    v-if="selectedMember"
                    class="rounded-xl border bg-muted/40 p-4"
                >
                    <p class="text-sm font-medium">Selected Member</p>

                    <p class="mt-2">
                        {{ selectedMember.name }}
                    </p>

                    <p class="text-xs text-muted-foreground">
                        {{ selectedMember.email }}
                    </p>
                </div>

                <p
                    v-if="form.errors.member_id"
                    class="text-sm text-destructive"
                >
                    {{ form.errors.member_id }}
                </p>

                <Button
                    type="submit"
                    class="w-full"
                    :disabled="!form.member_id || form.processing"
                >
                    Add Signer
                </Button>
            </form>
        </DialogContent>
    </Dialog>
</template>
