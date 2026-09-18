<script setup>
import { computed, ref, watch } from "vue";
import { LayoutTemplate, Check } from "lucide-vue-next";

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";

import { Button } from "@/components/ui/button";

const props = defineProps({
    open: { type: Boolean, default: false },
    templates: { type: Array, default: () => [] },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(["update:open", "use"]);

const selectedId = ref(null);

const selectedTemplate = computed(
    () => props.templates.find((t) => t.id === selectedId.value) ?? null,
);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) selectedId.value = null;
    },
);

function maxPage(template) {
    const pages = (template.signature_fields ?? []).map((f) => Number(f.page));

    return pages.length ? Math.max(...pages) : 0;
}

function use() {
    if (selectedTemplate.value) emit("use", selectedTemplate.value);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Use Template</DialogTitle>

                <DialogDescription>
                    Pick a template. Its fields replace every field currently on
                    this document. You assign a signer to each field afterwards.
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="templates.length === 0"
                class="rounded-xl border bg-muted/30 p-6 text-center text-sm text-muted-foreground"
            >
                No templates yet. Upload one from the Templates page first.
            </div>

            <div v-else class="max-h-[50vh] space-y-2 overflow-y-auto">
                <button
                    v-for="template in templates"
                    :key="template.id"
                    type="button"
                    class="flex w-full items-center gap-3 rounded-xl border p-3 text-left transition-colors hover:bg-muted/60"
                    :class="template.id === selectedId ? 'border-accent-ink bg-primary/5' : 'border-border'"
                    @click="selectedId = template.id"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-accent-ink">
                        <LayoutTemplate class="h-5 w-5" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ template.name }}</p>

                        <p class="text-xs text-muted-foreground">
                            {{ template.signature_fields.length }}
                            {{ template.signature_fields.length === 1 ? "field" : "fields" }}
                            <template v-if="maxPage(template)">
                                · up to page {{ maxPage(template) }}
                            </template>
                        </p>
                    </div>

                    <Check v-if="template.id === selectedId" class="h-4 w-4 shrink-0 text-accent-ink" />
                </button>
            </div>

            <DialogFooter class="grid gap-2 sm:flex sm:justify-end">
                <Button variant="outline" :disabled="processing" @click="emit('update:open', false)">
                    Cancel
                </Button>

                <Button :disabled="!selectedTemplate || processing" @click="use">
                    {{ processing ? "Applying..." : "Use" }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
