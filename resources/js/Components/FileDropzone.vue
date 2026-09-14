<script setup>
import { ref } from "vue";
import { Upload, FileText, X } from "lucide-vue-next";

const emit = defineEmits(["select"]);

const file = ref(null);
const dragging = ref(false);

const selectFile = (selectedFile) => {
    if (!selectedFile) return;

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
            class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-10 text-center cursor-pointer transition-all duration-200"
            :class="
                dragging
                    ? 'border-primary bg-primary/5'
                    : 'border-muted-foreground/25 hover:border-primary/50 hover:bg-muted/50'
            "
        >
            <Upload class="h-10 w-10 mb-3 text-muted-foreground" />

            <h3 class="font-medium">Drop document here</h3>

            <p class="text-sm text-muted-foreground mt-1">
                Drag & drop PDF or DOCX files
            </p>

            <p class="text-xs text-muted-foreground mt-2">
                Maximum size: 10 MB
            </p>

            <input
                type="file"
                class="hidden"
                accept=".pdf,.doc,.docx"
                @change="onInputChange"
            />
        </label>
    </div>
</template>
