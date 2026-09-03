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
    <div class="space-y-6">
        <!-- Toolbar -->

        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold">
                    {{ document.signers.length }}
                    {{ document.signers.length === 1 ? "Signer" : "Signers" }}
                </h3>

                <p class="text-sm text-muted-foreground">
                    Configure recipients who will sign this document.
                </p>
            </div>

            <AddSignerDialog
                v-if="document.status === 'draft'"
                :document="document"
                :members="members"
            />
        </div>

        <!-- Signers -->

        <SignersManager
            :document="document"
            :signer-field-counts="signerFieldCounts"
        />
    </div>
</template>
