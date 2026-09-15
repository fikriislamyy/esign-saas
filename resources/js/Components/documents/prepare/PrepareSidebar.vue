<script setup>
import { FileSignature, MousePointerClick, Info, LayoutTemplate, AlertTriangle, ChevronDown } from "lucide-vue-next";
import { ref, computed } from "vue";

import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";

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

    signers: {
        type: Array,
        default: () => [],
    },

    signatureFields: {
        type: Array,
        default: () => [],
    },

    freeFields: {
        type: Array,
        default: () => [],
    },

    workflowChanging: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["start-placement", "use-template", "discard-free-fields", "update-workflow"]);

const expandSigners = ref(false);

const isSequential = computed(() => {
    if (props.signers.length === 0) return false;
    return props.signers[0].signing_order > 0;
});
</script>

<template>
    <Card class="lg:sticky lg:top-6">
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
                    <span v-if="freeFields.length" class="text-amber-600 dark:text-amber-400">
                        · {{ freeFields.length }} unassigned
                    </span>
                </p>
            </div>

            <!-- Signers Workflow -->

            <div v-if="signers.length > 0" class="space-y-3">
                <button
                    type="button"
                    @click="expandSigners = !expandSigners"
                    class="flex w-full items-center justify-between rounded-xl border p-3 text-left hover:bg-muted/50"
                >
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                            Signers
                        </p>
                        <p class="mt-1 text-sm font-medium">
                            {{ isSequential ? "Sequential" : "Parallel" }} Signing
                        </p>
                    </div>
                    <ChevronDown
                        class="h-4 w-4 transition-transform"
                        :class="{ 'rotate-180': expandSigners }"
                    />
                </button>

                <div v-if="expandSigners" class="space-y-2 rounded-xl border p-3">
                    <div class="space-y-2">
                        <p v-for="signer in signers" :key="signer.id" class="text-xs text-muted-foreground">
                            <span class="font-medium">{{ signer.name }}</span>
                            <span v-if="isSequential" class="ml-1 text-primary">
                                #{{ signer.signing_order }}
                            </span>
                        </p>
                    </div>

                    <div class="border-t pt-2">
                        <Button
                            size="sm"
                            variant="outline"
                            class="w-full text-xs"
                            :disabled="workflowChanging"
                            @click="emit('update-workflow', !isSequential)"
                        >
                            Switch to {{ isSequential ? "Parallel" : "Sequential" }}
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Tools -->

            <div class="space-y-2">
                <p
                    class="text-xs font-semibold uppercase tracking-widest text-muted-foreground"
                >
                    Tools
                </p>

                <Button
                    class="w-full"
                    :disabled="editor.placingSignature"
                    @click="emit('start-placement')"
                >
                    <FileSignature class="mr-2 h-4 w-4" />

                    Add Signature Field
                </Button>

                <Button
                    variant="outline"
                    class="w-full"
                    @click="emit('use-template')"
                >
                    <LayoutTemplate class="mr-2 h-4 w-4" />

                    Use Template
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
                                Click anywhere on the PDF to place a field for
                                <strong>{{ editor.selectedMember?.name }}</strong>.
                            </p>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Unassigned Notice -->

            <div
                v-if="freeFields.length"
                class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4"
            >
                <div class="flex gap-3">
                    <AlertTriangle class="mt-0.5 h-4 w-4 text-amber-600 dark:text-amber-400" />

                    <div class="space-y-2">
                        <p class="text-sm font-medium">
                            {{ freeFields.length }} unassigned
                            {{ freeFields.length === 1 ? "field" : "fields" }}
                        </p>

                        <p class="text-sm text-muted-foreground">
                            Click each dashed field on the PDF to choose its signer.
                            Unassigned fields are not saved.
                        </p>

                        <Button variant="ghost" size="sm" @click="emit('discard-free-fields')">
                            Discard unassigned
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Instructions -->

            <div class="rounded-xl border bg-muted/30 p-4">
                <div class="flex gap-3">
                    <Info class="mt-0.5 h-4 w-4 text-muted-foreground" />

                    <div class="space-y-2 text-sm text-muted-foreground">
                        <p>1. Click <strong>Add Signature Field</strong> and choose a member.</p>

                        <p>2. Click on the PDF to place the field.</p>

                        <p>3. Drag and resize as needed.</p>

                        <p>Or click <strong>Use Template</strong>, then click each field to choose its signer.</p>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
