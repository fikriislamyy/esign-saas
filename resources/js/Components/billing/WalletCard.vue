<script setup>
import { computed } from "vue";
import { CreditCard, Plus, WalletCards } from "lucide-vue-next";

import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";

import TopUpDialog from "@/Components/billing/TopUpDialog.vue";

const props = defineProps({
    wallet: {
        type: Object,
        required: true,
    },
});

const formattedBalance = computed(() => {
    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: "USD",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(props.wallet.balanceUsdCents ?? 0) / 100);
});

const formattedIdr = computed(() => {
    if (
        props.wallet.balanceIdr === null ||
        props.wallet.balanceIdr === undefined
    ) {
        return null;
    }

    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(props.wallet.balanceIdr);
});

const formattedRate = computed(() => {
    const rate = props.wallet.usdToIdrRate;

    if (rate === null || rate === undefined) {
        return null;
    }

    return new Intl.NumberFormat("id-ID", {
        maximumFractionDigits: 2,
    }).format(rate);
});
</script>

<template>
    <Card class="overflow-hidden border-border/60 shadow-sm">
        <div
            class="flex flex-col gap-6 p-6 md:p-8 lg:flex-row lg:items-center lg:justify-between"
        >
            <!-- Balance -->
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <WalletCards class="h-5 w-5" />
                    </div>

                    <div>
                        <p class="text-sm font-medium">Available balance</p>

                        <p class="text-xs text-muted-foreground">
                            Shared across your organization
                        </p>
                    </div>
                </div>

                <div>
                    <div class="text-3xl font-bold tracking-tight sm:text-4xl">
                        {{ formattedBalance }}
                    </div>

                    <div
                        v-if="formattedIdr"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        ≈ {{ formattedIdr }}
                    </div>

                    <p
                        v-if="formattedRate"
                        class="mt-2 text-xs text-muted-foreground"
                    >
                        1 USD ≈ {{ formattedRate }} IDR
                    </p>

                    <p v-else class="mt-2 text-xs text-muted-foreground">
                        IDR conversion rate currently unavailable.
                    </p>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex w-full flex-col gap-2 sm:w-auto">
                <TopUpDialog :wallet="wallet">
                    <template #trigger>
                        <Button class="w-full sm:min-w-40">
                            <Plus class="mr-2 h-4 w-4" />
                            Top Up Wallet
                        </Button>
                    </template>
                </TopUpDialog>

                <div
                    class="flex items-center justify-center gap-2 text-xs text-muted-foreground"
                >
                    <CreditCard class="h-3.5 w-3.5" />

                    <span> Secure payment via Stripe </span>
                </div>
            </div>
        </div>

        <div class="border-t bg-muted/30 px-6 py-4 md:px-8">
            <div
                class="flex flex-col gap-1 text-xs sm:flex-row sm:items-center sm:justify-between"
            >
                <span class="text-muted-foreground">
                    Wallet accounting currency
                </span>

                <span class="font-medium text-foreground"> USD </span>
            </div>
        </div>
    </Card>
</template>
