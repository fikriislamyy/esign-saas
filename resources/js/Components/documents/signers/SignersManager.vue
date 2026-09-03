<script setup>
import { router, useForm } from "@inertiajs/vue3";

import { GripVertical, Trash2, Pencil, Save, X } from "lucide-vue-next";

import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";

import { computed, ref } from "vue";

import { useFeedback } from "@/Composables/useFeedback";

const props = defineProps({
    document: {
        type: Object,
        required: true,
    },

    signerFieldCounts: {
        type: Object,
        default: () => ({}),
    },
});

const { showConfirmation } = useFeedback();

const fieldCounts = computed(() => props.signerFieldCounts ?? {});

const editingOrder = ref(false);

const draggedSigner = ref(null);

const orderForm = useForm({
    signers: [],
});

function startEditOrder() {
    orderForm.signers = props.document.signers.map((signer) => ({
        id: signer.id,
        name: signer.name,
        email: signer.email,
        signing_order: signer.signing_order,
        status: signer.status,
    }));

    editingOrder.value = true;
}

function cancelEditOrder() {
    editingOrder.value = false;
}

function startDrag(index) {
    draggedSigner.value = index;
}

function dropSigner(index) {
    if (draggedSigner.value === null) {
        return;
    }

    const moved = orderForm.signers.splice(draggedSigner.value, 1)[0];

    orderForm.signers.splice(index, 0, moved);

    draggedSigner.value = null;
}

function saveOrder() {
    orderForm.signers = orderForm.signers.map(({ id }) => ({ id }));

    orderForm.post(route("documents.signers.reorder", props.document.id), {
        preserveScroll: true,

        onSuccess: () => {
            editingOrder.value = false;
        },
    });
}

function removeSigner(signer) {
    showConfirmation({
        title: "Remove Signer?",
        message: `Are you sure you want to remove ${signer.name} from this document? This action cannot be undone.`,
        confirmText: "Remove Signer",
        cancelText: "Cancel",

        onConfirm: () => {
            router.delete(route("documents.signers.destroy", signer.id), {
                preserveScroll: true,
            });
        },
    });
}
</script>

<template>
    <div class="space-y-6">
        <!-- Toolbar -->

        <div
            v-if="document.status === 'draft' && document.signers.length > 1"
            class="flex justify-end gap-2"
        >
            <Button
                v-if="!editingOrder"
                variant="outline"
                @click="startEditOrder"
            >
                <Pencil class="mr-2 h-4 w-4" />

                Edit Order
            </Button>

            <template v-else>
                <Button variant="outline" @click="cancelEditOrder">
                    <X class="mr-2 h-4 w-4" />

                    Cancel
                </Button>

                <Button @click="saveOrder">
                    <Save class="mr-2 h-4 w-4" />

                    Save Order
                </Button>
            </template>
        </div>

        <!-- NORMAL MODE -->

        <div v-if="!editingOrder" class="space-y-3">
            <div
                v-for="signer in document.signers"
                :key="signer.id"
                class="rounded-xl border p-4"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="font-semibold">
                            {{ signer.name }}
                        </p>

                        <p class="text-sm text-muted-foreground">
                            {{ signer.email }}
                        </p>

                        <p class="mt-2 text-xs text-muted-foreground">
                            {{ fieldCounts[signer.id] || 0 }}
                            signature field(s)
                        </p>
                    </div>

                    <div class="flex flex-col items-end gap-2">
                        <Badge variant="outline">
                            {{
                                signer.signing_order === 0
                                    ? "Parallel"
                                    : `Order ${signer.signing_order}`
                            }}
                        </Badge>

                        <Badge
                            v-if="signer.status === 'pending'"
                            variant="secondary"
                        >
                            Pending
                        </Badge>

                        <Badge
                            v-else-if="signer.status === 'email_sent'"
                            variant="warning"
                        >
                            Waiting
                        </Badge>

                        <Badge
                            v-else-if="signer.status === 'sent'"
                            variant="warning"
                        >
                            Waiting
                        </Badge>

                        <Badge
                            v-else-if="signer.status === 'signed'"
                            variant="success"
                        >
                            Signed
                        </Badge>

                        <Badge v-else variant="outline">
                            {{ signer.status }}
                        </Badge>

                        <Badge
                            v-if="(fieldCounts[signer.id] || 0) === 0"
                            variant="destructive"
                        >
                            No Signature Field
                        </Badge>

                        <Button
                            v-if="document.status === 'draft'"
                            size="icon"
                            variant="ghost"
                            @click="removeSigner(signer)"
                        >
                            <Trash2 class="h-4 w-4 text-destructive" />
                        </Button>
                    </div>
                </div>
            </div>

            <div
                v-if="!document.signers.length"
                class="rounded-xl border border-dashed py-10 text-center text-muted-foreground"
            >
                No signers added yet.
            </div>
        </div>

        <!-- REORDER MODE -->

        <div v-else class="space-y-3">
            <div
                v-for="(signer, index) in orderForm.signers"
                :key="signer.id"
                draggable="true"
                @dragstart="startDrag(index)"
                @dragover.prevent
                @drop="dropSigner(index)"
                class="flex cursor-move items-center gap-4 rounded-xl border p-4"
            >
                <GripVertical class="h-5 w-5 text-muted-foreground" />

                <div class="flex-1">
                    <p class="font-medium">
                        {{ signer.name }}
                    </p>

                    <p class="text-sm text-muted-foreground">
                        {{ signer.email }}
                    </p>
                </div>

                <Badge variant="outline">
                    Order
                    {{ index + 1 }}
                </Badge>
            </div>

            <div class="rounded-xl bg-muted p-4 text-sm text-muted-foreground">
                Drag signers to change the signing order.
            </div>
        </div>
    </div>
</template>
