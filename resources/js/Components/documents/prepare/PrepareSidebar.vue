<script setup>
import { FileSignature, MousePointerClick, Info } from "lucide-vue-next";

import SignerSelector from "./SignerSelector.vue";

import { Button } from "@/components/ui/button";

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";

const props = defineProps({
    document: {
        type: Object,
        required: true,
    },

    editor: {
        type: Object,
        required: true,
    },

    signatureFields: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(["start-placement"]);
</script>

<template>
    <Card class="sticky top-6">
        <CardHeader>
            <CardTitle> Prepare Document </CardTitle>

            <CardDescription>
                Configure signature placement before sending.
            </CardDescription>
        </CardHeader>

        <CardContent class="space-y-6">
            <!-- Summary -->

            <div class="rounded-xl border bg-muted/30 p-4">
                <p class="truncate text-sm font-medium">
                    {{ document.name }}
                </p>

                <p class="mt-2 text-sm text-muted-foreground">
                    {{ signatureFields.length }}
                    {{ signatureFields.length === 1 ? "Field" : "Fields" }}
                </p>
            </div>

            <!-- Signer -->

            <SignerSelector :signers="document.signers" :editor="editor" />

            <!-- Tools -->

            <div class="space-y-2">
                <p
                    class="text-xs font-semibold uppercase tracking-widest text-muted-foreground"
                >
                    Tools
                </p>

                <Button
                    class="w-full"
                    :disabled="!editor.selectedSigner"
                    @click="emit('start-placement')"
                >
                    <FileSignature class="mr-2 h-4 w-4" />

                    Add Signature Field
                </Button>
            </div>

            <!-- Placement Mode -->

            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition duration-150"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-if="editor.placingSignature"
                    class="rounded-xl border border-primary/20 bg-primary/10 p-4"
                >
                    <div class="flex gap-3">
                        <MousePointerClick
                            class="mt-0.5 h-4 w-4 text-primary"
                        />

                        <div class="space-y-1">
                            <p class="text-sm font-medium text-primary">
                                Placement Mode
                            </p>

                            <p class="text-sm text-muted-foreground">
                                Click anywhere on the PDF to place the signature
                                field.
                            </p>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Instructions -->

            <div class="rounded-xl border bg-muted/30 p-4">
                <div class="flex gap-3">
                    <Info class="mt-0.5 h-4 w-4 text-muted-foreground" />

                    <div class="space-y-2 text-sm text-muted-foreground">
                        <p>1. Select a signer.</p>

                        <p>
                            2. Click
                            <strong>Add Signature Field</strong>.
                        </p>

                        <p>3. Click on the PDF.</p>

                        <p>4. Drag and resize as needed.</p>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
