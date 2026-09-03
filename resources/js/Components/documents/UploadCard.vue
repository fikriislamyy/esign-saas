<script setup>
import FileDropzone from "@/Components/FileDropzone.vue";

import { Upload, FileText, Loader2, X } from "lucide-vue-next";

import { Button } from "@/components/ui/button";

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(["upload"]);

function selectFile(file) {
    props.form.file = file;
}

function removeFile() {
    props.form.file = null;
}

function formatSize(bytes) {
    if (!bytes) return "";

    const kb = bytes / 1024;

    if (kb < 1024) {
        return `${kb.toFixed(1)} KB`;
    }

    return `${(kb / 1024).toFixed(2)} MB`;
}
</script>

<template>
    <div class="space-y-6">
        <FileDropzone @select="selectFile" />

        <!-- Selected File -->

        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="form.file" class="rounded-xl border bg-muted/40 p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10"
                        >
                            <FileText class="h-5 w-5 text-primary" />
                        </div>

                        <div class="min-w-0">
                            <p class="truncate font-medium">
                                {{ form.file.name }}
                            </p>

                            <p class="text-sm text-muted-foreground">
                                {{ formatSize(form.file.size) }}
                            </p>
                        </div>
                    </div>

                    <Button variant="ghost" size="icon" @click="removeFile">
                        <X class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </Transition>

        <!-- Error -->

        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
        >
            <div
                v-if="form.errors.file"
                class="rounded-xl border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive"
            >
                {{ form.errors.file }}
            </div>
        </Transition>

        <!-- Actions -->

        <div class="flex justify-end">
            <Button
                size="lg"
                class="min-w-[180px]"
                :disabled="!form.file || form.processing"
                @click="$emit('upload')"
            >
                <Loader2
                    v-if="form.processing"
                    class="mr-2 h-4 w-4 animate-spin"
                />

                <Upload v-else class="mr-2 h-4 w-4" />

                {{ form.processing ? "Uploading..." : "Upload Document" }}
            </Button>
        </div>
    </div>
</template>
