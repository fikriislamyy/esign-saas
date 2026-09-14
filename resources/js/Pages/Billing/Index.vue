<script setup>
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import { ReceiptText, WalletCards } from "lucide-vue-next";

import AppLayout from "@/Layouts/AppLayout.vue";

import PageHeader from "@/Components/page/PageHeader.vue";
import PageSection from "@/Components/page/PageSection.vue";
import FadeIn from "@/Components/animations/FadeIn.vue";

import WalletCard from "@/Components/billing/WalletCard.vue";
import BillingStats from "@/Components/billing/BillingStats.vue";
import TransactionTable from "@/Components/billing/TransactionTable.vue";

const props = defineProps({
    wallet: {
        type: Object,
        required: true,
    },

    stats: {
        type: Object,
        required: true,
    },

    transactions: {
        type: Object,
        required: true,
    },
});

const hasTransactions = computed(() => {
    return (
        Array.isArray(props.transactions?.data) &&
        props.transactions.data.length > 0
    );
});
</script>

<template>
    <Head title="Billing" />

    <AppLayout>
        <div class="space-y-8">
            <FadeIn :delay="100" type="fade">
                <PageHeader
                    title="Billing"
                    description="Manage your organization's wallet and view billing activity."
                    :icon="WalletCards"
                />
            </FadeIn>

            <FadeIn :delay="200" type="scale">
                <WalletCard :wallet="wallet" />
            </FadeIn>

            <FadeIn :delay="300" type="scale">
                <BillingStats :stats="stats" />
            </FadeIn>

            <FadeIn :delay="400" type="scale">
                <PageSection
                    title="Transaction History"
                    description="A complete record of wallet top-ups and signature charges."
                    :padding="false"
                >
                    <template v-if="hasTransactions">
                        <TransactionTable :transactions="transactions" />
                    </template>

                    <template v-else>
                        <div
                            class="flex min-h-[280px] flex-col items-center justify-center px-6 py-12 text-center"
                        >
                            <div
                                class="flex h-14 w-14 items-center justify-center rounded-2xl border bg-muted/60"
                            >
                                <ReceiptText
                                    class="h-6 w-6 text-muted-foreground"
                                />
                            </div>

                            <h3 class="mt-4 text-sm font-semibold">
                                No transactions yet
                            </h3>

                            <p
                                class="mt-1 max-w-sm text-sm leading-6 text-muted-foreground"
                            >
                                Your wallet activity will appear here once you
                                make a top-up or complete a signature.
                            </p>
                        </div>
                    </template>
                </PageSection>
            </FadeIn>
        </div>
    </AppLayout>
</template>
