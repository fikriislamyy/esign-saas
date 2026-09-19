<script setup>
import { ref } from "vue";
import { Upload, FileText, X } from "lucide-vue-next";

const emit = defineEmits(["select", "error"]);

const MAX_BYTES = 5 * 1024 * 1024;

const file = ref(null);
const dragging = ref(false);

const selectFile = (selectedFile) => {
    if (!selectedFile) return;

    if (!selectedFile.name.toLowerCase().endsWith(".pdf")) {
        emit("error", "Only PDF files can be uploaded.");
        return;
    }

    if (selectedFile.size > MAX_BYTES) {
        emit("error", "The file must be 5 MB or smaller.");
        return;
    }

    file.value = selectedFile;
    emit("select", selectedFile);
};

const onInputChange = (event) => {
    selectFile(event.target.files[0]);
};

const onDrop = (event) => {
    event.preventDefault();

    dragging.value = false;

    if (event.dataTransfer.files.length) {
        selectFile(event.dataTransfer.files[0]);
    }
};

const removeFile = () => {
    file.value = null;
    emit("select", null);
};

const formatSize = (bytes) => {
    if (!bytes) return "";

    return (bytes / 1024).toFixed(1) + " KB";
};
</script>

<template>
    <div class="space-y-3">
        <label
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop="onDrop"
            class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-10 text-center cursor-pointer transition-[border-color,background-color] duration-200"
            :class="
                dragging
                    ? 'border-accent-ink bg-primary/5'
                    : 'border-muted-foreground/25 hover:border-accent-ink/50 hover:bg-muted/50'
            "
        >
            <Upload class="h-10 w-10 mb-3 text-muted-foreground" />

            <h3 class="font-medium">Drop document here</h3>

            <p class="text-sm text-muted-foreground mt-1">
                Drag & drop a PDF file
            </p>

            <p class="text-xs text-muted-foreground mt-2">
                Maximum size: 5 MB
            </p>

            <input
                type="file"
                class="hidden"
                accept="application/pdf,.pdf"
                @change="onInputChange"
            />
        </label>
    </div>
</template>
