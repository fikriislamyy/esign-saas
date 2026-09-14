<script setup>
const props = defineProps({
    template: {
        type: Object,
        required: true,
    },
});

function formatSize(bytes) {
    if (!bytes) return "-";

    const kb = bytes / 1024;

    if (kb < 1024) {
        return `${kb.toFixed(1)} KB`;
    }

    return `${(kb / 1024).toFixed(2)} MB`;
}
</script>

<template>
    <dl class="grid gap-6 sm:grid-cols-2">
        <!-- Signature Fields -->

        <div class="space-y-2">
            <dt class="text-sm text-muted-foreground">Signature Fields</dt>
            <dd class="font-medium">{{ template.signature_fields_count ?? 0 }}</dd>
        </div>

        <!-- File Type -->

        <div class="space-y-2">
            <dt class="text-sm text-muted-foreground">File Type</dt>

            <dd class="font-medium">
                {{ template.mime_type }}
            </dd>
        </div>

        <!-- File Size -->

        <div class="space-y-2">
            <dt class="text-sm text-muted-foreground">File Size</dt>

            <dd class="font-medium">
                {{ formatSize(template.file_size) }}
            </dd>
        </div>

        <!-- Uploaded At -->

        <div class="space-y-2">
            <dt class="text-sm text-muted-foreground">Uploaded At</dt>

            <dd class="font-medium">
                {{ template.created_at_human }}
            </dd>
        </div>
    </dl>
</template>
