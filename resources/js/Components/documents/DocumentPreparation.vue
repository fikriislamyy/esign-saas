<script setup>
import { computed } from "vue";

import { CheckCircle2, AlertCircle, Users, PenSquare } from "lucide-vue-next";

import { Badge } from "@/components/ui/badge";

const props = defineProps({
    document: {
        type: Object,
        required: true,
    },

    signerFieldCounts: {
        type: Object,
        default: () => ({}),
    },
});

const fieldCounts = computed(() => props.signerFieldCounts ?? {});

const totalSigners = computed(() => props.document.signers?.length ?? 0);

const readySigners = computed(() => {
    return (props.document.signers ?? []).filter((signer) => {
        return (fieldCounts.value[signer.id] ?? 0) > 0;
    }).length;
});

const completion = computed(() => {
    if (totalSigners.value === 0) return 0;

    return Math.round((readySigners.value / totalSigners.value) * 100);
});

const isReady = computed(() => {
    return totalSigners.value > 0 && readySigners.value === totalSigners.value;
});
</script>

<template>
    <div class="space-y-6">
        <!-- Overall status -->

        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium">Document Status</p>

                <p class="text-sm text-muted-foreground">
                    {{ readySigners }} of {{ totalSigners }} signers ready
                </p>
            </div>

            <Badge :variant="isReady ? 'success' : 'warning'">
                {{ isReady ? "Ready" : "Incomplete" }}
            </Badge>
        </div>

        <!-- Progress -->

        <div>
            <div class="mb-2 flex justify-between text-sm">
                <span>Preparation</span>

                <span>{{ completion }}%</span>
            </div>

            <div class="h-2 rounded-full bg-muted overflow-hidden">
                <div
                    class="h-full rounded-full bg-primary transition-all"
                    :style="{ width: `${completion}%` }"
                />
            </div>
        </div>

        <!-- Stats -->

        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-xl border p-4">
                <Users class="mb-2 h-5 w-5 text-muted-foreground" />

                <p class="text-2xl font-bold">
                    {{ totalSigners }}
                </p>

                <p class="text-sm text-muted-foreground">Total Signers</p>
            </div>

            <div class="rounded-xl border p-4">
                <PenSquare class="mb-2 h-5 w-5 text-muted-foreground" />

                <p class="text-2xl font-bold">
                    {{ readySigners }}
                </p>

                <p class="text-sm text-muted-foreground">Ready</p>
            </div>
        </div>

        <!-- Checklist -->

        <div class="space-y-3">
            <div
                v-for="signer in document.signers ?? []"
                :key="signer.id"
                class="flex items-center justify-between rounded-lg border p-3"
            >
                <div>
                    <p class="font-medium">
                        {{ signer.name }}
                    </p>

                    <p class="text-sm text-muted-foreground">
                        {{ signer.email }}
                    </p>
                </div>

                <Badge
                    v-if="(fieldCounts[signer.id] ?? 0) > 0"
                    variant="success"
                >
                    <CheckCircle2 class="mr-1 h-3.5 w-3.5" />
                    Ready
                </Badge>

                <Badge v-else variant="warning">
                    <AlertCircle class="mr-1 h-3.5 w-3.5" />
                    Incomplete
                </Badge>
            </div>
        </div>
    </div>
</template>
