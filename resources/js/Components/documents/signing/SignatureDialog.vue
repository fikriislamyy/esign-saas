<script setup>
import { ref, watch, nextTick, onBeforeUnmount } from "vue";

import SignaturePad from "signature_pad";

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";

import { Button } from "@/components/ui/button";

import { Eraser, PenLine, Save } from "lucide-vue-next";

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["update:open", "save"]);

const canvas = ref(null);

let signaturePad = null;

function resizeCanvas() {
    if (!canvas.value || !signaturePad) {
        return;
    }

    const ratio = Math.max(window.devicePixelRatio || 1, 1);

    const rect = canvas.value.getBoundingClientRect();

    canvas.value.width = rect.width * ratio;

    canvas.value.height = rect.height * ratio;

    const context = canvas.value.getContext("2d");

    context.scale(ratio, ratio);

    signaturePad.clear();
}

function clear() {
    signaturePad?.clear();
}

function save() {
    if (!signaturePad || signaturePad.isEmpty()) {
        return;
    }

    emit("save", signaturePad.toDataURL("image/png"));
}

watch(
    () => props.open,
    async (open) => {
        if (!open) {
            return;
        }

        await nextTick();

        signaturePad?.off();

        signaturePad = new SignaturePad(canvas.value, {
            penColor: "rgb(15, 23, 42)",
            minWidth: 0.8,
            maxWidth: 2.5,
        });

        resizeCanvas();
    },
);

onBeforeUnmount(() => {
    signaturePad?.off();
});
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <PenLine class="h-5 w-5" />

                    Add Your Signature
                </DialogTitle>

                <DialogDescription>
                    Draw your signature in the area below.
                </DialogDescription>
            </DialogHeader>

            <div class="overflow-hidden rounded-xl border bg-white">
                <canvas ref="canvas" class="h-[220px] w-full touch-none" />
            </div>

            <DialogFooter class="flex-row justify-between gap-2">
                <Button variant="outline" @click="clear">
                    <Eraser class="mr-2 h-4 w-4" />

                    Clear
                </Button>

                <Button @click="save">
                    <Save class="mr-2 h-4 w-4" />

                    Apply Signature
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
