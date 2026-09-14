<script setup>
import { Link } from "@inertiajs/vue3";

import { Send, FilePenLine, Eye, Download } from "lucide-vue-next";

import { Button } from "@/components/ui/button";

const props = defineProps({
    document: {
        type: Object,
        required: true,
    },

    canSend: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["send", "preview", "download"]);
</script>

<template>
    <div class="flex flex-col items-end gap-2">
        <!-- Actions -->

        <div class="flex flex-wrap justify-end gap-2">
            <!-- Draft -->

            <template
                v-if="
                    document.status === 'draft' && document.signers.length > 0
                "
            >
                <Button :disabled="!canSend" @click="emit('send')">
                    <Send class="mr-2 h-4 w-4" />
                    Send for Signature
                </Button>

                <Button variant="outline" as-child>
                    <Link :href="route('documents.prepare', document.id)">
                        <FilePenLine class="mr-2 h-4 w-4" />
                        Prepare Document
                    </Link>
                </Button>
            </template>

            <!-- Sent -->

            <template v-else-if="document.status === 'sent'">
                <Button variant="outline" @click="emit('preview')">
                    <Eye class="mr-2 h-4 w-4" />
                    Preview Document
                </Button>
            </template>

            <!-- Completed -->

            <template v-else-if="document.status === 'completed'">
                <Button variant="outline" @click="emit('preview')">
                    <Eye class="mr-2 h-4 w-4" />
                    Preview Signed PDF
                </Button>

                <Button @click="emit('download')">
                    <Download class="mr-2 h-4 w-4" />
                    Download PDF
                </Button>
            </template>
        </div>

        <!-- Validation -->

        <p
            v-if="
                document.status === 'draft' &&
                document.signers.length &&
                !canSend
            "
            class="max-w-sm text-right text-sm text-destructive"
        >
            Every signer must have at least one signature field before sending
            the document.
        </p>
    </div>
</template>
