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
    <div class="min-w-0 space-y-6">
        <!-- Toolbar -->

        <div
            v-if="document.status === 'draft' && document.signers.length > 1"
            class="flex flex-wrap justify-end gap-2"
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

        <div v-if="!editingOrder" class="min-w-0 space-y-3">
            <div
                v-for="signer in document.signers"
                :key="signer.id"
                class="min-w-0 rounded-xl border p-4"
            >
                <div
                    class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                >
                    <!-- Signer information -->

                    <div class="min-w-0 flex-1">
                        <p class="break-words font-semibold">
                            {{ signer.name }}
                        </p>

                        <p class="break-all text-sm text-muted-foreground">
                            {{ signer.email }}
                        </p>

                        <p class="mt-2 text-xs text-muted-foreground">
                            {{ fieldCounts[signer.id] || 0 }}
                            signature field(s)
                        </p>
                    </div>

                    <!-- Status / actions -->

                    <div
                        class="flex min-w-0 flex-wrap items-center gap-2 sm:flex-col sm:items-end"
                    >
                        <Badge
                            variant="outline"
                            class="max-w-full whitespace-normal text-center"
                        >
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
                            v-else-if="
                                signer.status === 'email_sent' ||
                                signer.status === 'sent'
                            "
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
                            class="max-w-full whitespace-normal text-center"
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

        <div v-else class="min-w-0 space-y-3">
            <div
                v-for="(signer, index) in orderForm.signers"
                :key="signer.id"
                draggable="true"
                @dragstart="startDrag(index)"
                @dragover.prevent
                @drop="dropSigner(index)"
                class="flex min-w-0 cursor-move items-start gap-4 rounded-xl border p-4"
            >
                <GripVertical
                    class="mt-1 h-5 w-5 shrink-0 text-muted-foreground"
                />

                <div class="min-w-0 flex-1">
                    <p class="break-words font-medium">
                        {{ signer.name }}
                    </p>

                    <p class="break-all text-sm text-muted-foreground">
                        {{ signer.email }}
                    </p>
                </div>

                <Badge
                    variant="outline"
                    class="shrink-0 whitespace-normal text-center"
                >
                    Order {{ index + 1 }}
                </Badge>
            </div>

            <div class="rounded-xl bg-muted p-4 text-sm text-muted-foreground">
                Drag signers to change the signing order.
            </div>
        </div>
    </div>
</template>
