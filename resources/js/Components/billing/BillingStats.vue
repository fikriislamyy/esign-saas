<script setup>
import { computed } from "vue";
import { FileSignature, TrendingDown, WalletCards } from "lucide-vue-next";

import { Card } from "@/components/ui/card";

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },
});

function formatUsd(cents) {
    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: "USD",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(cents ?? 0) / 100);
}

const monthlySpent = computed(() => {
    return formatUsd(props.stats.monthlySpentUsdCents);
});

const totalSpent = computed(() => {
    return formatUsd(props.stats.totalSpentUsdCents);
});
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <!-- Monthly spend -->
        <Card class="border-border/60 shadow-sm">
            <div class="flex items-center gap-4 p-5">
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-muted text-muted-foreground"
                >
                    <TrendingDown class="h-5 w-5" />
                </div>

                <div class="min-w-0">
                    <p class="text-sm text-muted-foreground">This month</p>

                    <p class="mt-0.5 text-xl font-semibold tabular-nums">
                        {{ monthlySpent }}
                    </p>

                    <p class="mt-1 text-xs text-muted-foreground">
                        Signature spending
                    </p>
                </div>
            </div>
        </Card>

        <!-- Monthly signatures -->
        <Card class="border-border/60 shadow-sm">
            <div class="flex items-center gap-4 p-5">
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <FileSignature class="h-5 w-5" />
                </div>

                <div class="min-w-0">
                    <p class="text-sm text-muted-foreground">
                        Signatures this month
                    </p>

                    <p class="mt-0.5 text-xl font-semibold tabular-nums">
                        {{ stats.monthlySignatureCount }}
                    </p>

                    <p class="mt-1 text-xs text-muted-foreground">
                        Completed signatures
                    </p>
                </div>
            </div>
        </Card>

        <!-- Total spend -->
        <Card class="border-border/60 shadow-sm sm:col-span-2 xl:col-span-1">
            <div class="flex items-center gap-4 p-5">
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-muted text-muted-foreground"
                >
                    <WalletCards class="h-5 w-5" />
                </div>

                <div class="min-w-0">
                    <p class="text-sm text-muted-foreground">
                        Total signature spending
                    </p>

                    <p class="mt-0.5 text-xl font-semibold tabular-nums">
                        {{ totalSpent }}
                    </p>

                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ stats.totalSignatureCount }} signatures all time
                    </p>
                </div>
            </div>
        </Card>
    </div>
</template>
