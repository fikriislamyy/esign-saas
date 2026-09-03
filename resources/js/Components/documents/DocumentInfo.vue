<script setup>
import { computed } from "vue";

import { Badge } from "@/components/ui/badge";

const props = defineProps({
    document: {
        type: Object,
        required: true,
    },
});

const signedCount = computed(() => {
    return props.document.signers.filter((signer) => signer.status === "signed")
        .length;
});

function formatSize(bytes) {
    if (!bytes) return "-";

    const kb = bytes / 1024;

    if (kb < 1024) {
        return `${kb.toFixed(1)} KB`;
    }

    return `${(kb / 1024).toFixed(2)} MB`;
}

function statusVariant(status) {
    switch (status) {
        case "draft":
            return "secondary";

        case "sent":
            return "warning";

        case "completed":
            return "success";

        default:
            return "secondary";
    }
}

function statusLabel(status) {
    switch (status) {
        case "draft":
            return "Draft";

        case "sent":
            return "Pending Signature";

        case "completed":
            return "Completed";

        default:
            return status;
    }
}
</script>

<template>
    <dl class="grid gap-6 sm:grid-cols-2">
        <!-- Status -->

        <div class="space-y-2">
            <dt class="text-sm text-muted-foreground">Status</dt>

            <dd>
                <Badge :variant="statusVariant(document.status)">
                    {{ statusLabel(document.status) }}
                </Badge>
            </dd>
        </div>

        <!-- Progress -->

        <div v-if="document.status !== 'draft'" class="space-y-2">
            <dt class="text-sm text-muted-foreground">Signing Progress</dt>

            <dd class="font-medium">
                {{ signedCount }} / {{ document.signers.length }}
                Signed
            </dd>
        </div>

        <!-- Uploaded By -->

        <div class="space-y-2">
            <dt class="text-sm text-muted-foreground">Uploaded By</dt>

            <dd class="font-medium">
                {{ document.uploader.name }}
            </dd>
        </div>

        <!-- File Type -->

        <div class="space-y-2">
            <dt class="text-sm text-muted-foreground">File Type</dt>

            <dd class="font-medium">
                {{ document.mime_type }}
            </dd>
        </div>

        <!-- File Size -->

        <div class="space-y-2">
            <dt class="text-sm text-muted-foreground">File Size</dt>

            <dd class="font-medium">
                {{ formatSize(document.file_size) }}
            </dd>
        </div>

        <!-- Uploaded At -->

        <div class="space-y-2">
            <dt class="text-sm text-muted-foreground">Uploaded At</dt>

            <dd class="font-medium">
                {{ document.created_at_human }}
            </dd>
        </div>
    </dl>
</template>
