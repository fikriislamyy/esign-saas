<script setup>
import AddSignerDialog from "./AddSignerDialog.vue";
import SignersManager from "./SignersManager.vue";

const props = defineProps({
    document: {
        type: Object,
        required: true,
    },

    members: {
        type: Array,
        required: true,
    },

    signerFieldCounts: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="min-w-0 space-y-6">
        <!-- Toolbar -->

        <div
            class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="min-w-0 flex-1">
                <h3 class="text-lg font-semibold">
                    {{ document.signers.length }}
                    {{ document.signers.length === 1 ? "Signer" : "Signers" }}
                </h3>

                <p class="max-w-full break-words text-sm text-muted-foreground">
                    Configure recipients who will sign this document.
                </p>
            </div>

            <div class="shrink-0">
                <AddSignerDialog
                    v-if="document.status === 'draft'"
                    :document="document"
                    :members="members"
                />
            </div>
        </div>

        <!-- Signers -->

        <SignersManager
            :document="document"
            :signer-field-counts="signerFieldCounts"
        />
    </div>
</template>
