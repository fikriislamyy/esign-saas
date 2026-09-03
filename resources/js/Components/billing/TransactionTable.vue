<script setup>
import { computed } from "vue";
import { router } from "@inertiajs/vue3";
import {
    ArrowDownToLine,
    ArrowUpFromLine,
    FileSignature,
} from "lucide-vue-next";

import { Button } from "@/components/ui/button";

const props = defineProps({
    transactions: {
        type: Object,
        required: true,
    },
});

function formatUsd(cents) {
    const value = Number(cents ?? 0) / 100;

    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: "USD",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

function formatSourceAmount(amount, currency) {
    if (amount === null || amount === undefined) {
        return null;
    }

    const numericAmount = Number(amount);

    if (currency === "IDR") {
        return new Intl.NumberFormat("id-ID", {
            style: "currency",
            currency: "IDR",
            maximumFractionDigits: 0,
        }).format(numericAmount);
    }

    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: currency || "USD",
        maximumFractionDigits: 2,
    }).format(numericAmount);
}

function formatDate(date) {
    if (!date) {
        return "—";
    }

    return new Intl.DateTimeFormat("en-US", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(date));
}

function getTypeLabel(transaction) {
    switch (transaction.type) {
        case "topup":
            return "Wallet top-up";

        case "signature":
            return "Signature";

        case "refund":
            return "Refund";

        case "adjustment":
            return "Adjustment";

        default:
            return transaction.type
                ? transaction.type.charAt(0).toUpperCase() +
                      transaction.type.slice(1)
                : "Transaction";
    }
}

function getDescription(transaction) {
    if (transaction.description) {
        return transaction.description;
    }

    return getTypeLabel(transaction);
}

function isCredit(transaction) {
    return Number(transaction.amount_usd_cents) > 0;
}

function getIcon(transaction) {
    if (transaction.type === "topup") {
        return ArrowDownToLine;
    }

    if (transaction.type === "signature") {
        return FileSignature;
    }

    return isCredit(transaction) ? ArrowDownToLine : ArrowUpFromLine;
}

function getAmountClass(transaction) {
    return isCredit(transaction) ? "text-foreground" : "text-muted-foreground";
}

function getFormattedAmount(transaction) {
    const amount = formatUsd(Math.abs(transaction.amount_usd_cents));

    return isCredit(transaction) ? `+${amount}` : `-${amount}`;
}

function changePage(url) {
    if (!url) {
        return;
    }

    router.get(
        url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

const pages = computed(() => {
    return props.transactions.links ?? [];
});
</script>

<template>
    <div>
        <!-- Desktop -->
        <div class="hidden md:block">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr
                            class="border-b bg-muted/30 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            <th class="px-6 py-3 font-medium">Transaction</th>

                            <th class="px-6 py-3 font-medium">Source</th>

                            <th class="px-6 py-3 text-right font-medium">
                                Amount
                            </th>

                            <th class="px-6 py-3 text-right font-medium">
                                Balance
                            </th>

                            <th class="px-6 py-3 text-right font-medium">
                                Date
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y">
                        <tr
                            v-for="transaction in transactions.data"
                            :key="transaction.id"
                            class="transition-colors hover:bg-muted/20"
                        >
                            <!-- Transaction -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-muted/70"
                                    >
                                        <component
                                            :is="getIcon(transaction)"
                                            class="h-4 w-4 text-muted-foreground"
                                        />
                                    </div>

                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium">
                                            {{ getDescription(transaction) }}
                                        </p>

                                        <p
                                            class="mt-0.5 text-xs text-muted-foreground"
                                        >
                                            {{ getTypeLabel(transaction) }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <!-- Source -->
                            <td class="px-6 py-4">
                                <div
                                    v-if="transaction.source_amount"
                                    class="text-sm"
                                >
                                    {{
                                        formatSourceAmount(
                                            transaction.source_amount,
                                            transaction.source_currency,
                                        )
                                    }}
                                </div>

                                <div
                                    v-if="transaction.source_currency"
                                    class="mt-0.5 text-xs text-muted-foreground"
                                >
                                    {{ transaction.source_currency }}
                                </div>

                                <span
                                    v-else
                                    class="text-sm text-muted-foreground"
                                >
                                    —
                                </span>
                            </td>

                            <!-- Amount -->
                            <td class="px-6 py-4 text-right">
                                <span
                                    class="text-sm font-semibold tabular-nums"
                                    :class="getAmountClass(transaction)"
                                >
                                    {{ getFormattedAmount(transaction) }}
                                </span>
                            </td>

                            <!-- Balance -->
                            <td class="px-6 py-4 text-right">
                                <span class="text-sm tabular-nums">
                                    {{
                                        formatUsd(
                                            transaction.balance_after_usd_cents,
                                        )
                                    }}
                                </span>
                            </td>

                            <!-- Date -->
                            <td class="whitespace-nowrap px-6 py-4 text-right">
                                <span class="text-sm text-muted-foreground">
                                    {{ formatDate(transaction.created_at) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile -->
        <div class="divide-y md:hidden">
            <div
                v-for="transaction in transactions.data"
                :key="transaction.id"
                class="p-4"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-muted/70"
                    >
                        <component
                            :is="getIcon(transaction)"
                            class="h-4 w-4 text-muted-foreground"
                        />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">
                                    {{ getDescription(transaction) }}
                                </p>

                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ getTypeLabel(transaction) }}
                                </p>
                            </div>

                            <p
                                class="shrink-0 text-sm font-semibold tabular-nums"
                                :class="getAmountClass(transaction)"
                            >
                                {{ getFormattedAmount(transaction) }}
                            </p>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-3 text-xs">
                            <div>
                                <p class="text-muted-foreground">Balance</p>

                                <p class="mt-0.5 font-medium tabular-nums">
                                    {{
                                        formatUsd(
                                            transaction.balance_after_usd_cents,
                                        )
                                    }}
                                </p>
                            </div>

                            <div>
                                <p class="text-muted-foreground">Date</p>

                                <p class="mt-0.5 font-medium">
                                    {{ formatDate(transaction.created_at) }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="transaction.source_amount"
                            class="mt-3 rounded-lg bg-muted/30 px-3 py-2"
                        >
                            <p class="text-[11px] text-muted-foreground">
                                Payment source
                            </p>

                            <p class="mt-0.5 text-xs font-medium">
                                {{
                                    formatSourceAmount(
                                        transaction.source_amount,
                                        transaction.source_currency,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div
            v-if="pages.length > 3"
            class="flex flex-col gap-3 border-t px-4 py-4 sm:flex-row sm:items-center sm:justify-between md:px-6"
        >
            <p class="text-xs text-muted-foreground">
                Showing
                <span class="font-medium text-foreground">
                    {{ transactions.from ?? 0 }}
                </span>
                to
                <span class="font-medium text-foreground">
                    {{ transactions.to ?? 0 }}
                </span>
                of
                <span class="font-medium text-foreground">
                    {{ transactions.total ?? 0 }}
                </span>
                transactions
            </p>

            <div class="flex items-center gap-1">
                <template v-for="(page, index) in pages" :key="index">
                    <Button
                        v-if="page.url"
                        size="sm"
                        :variant="page.active ? 'default' : 'outline'"
                        class="h-8 min-w-8 px-2"
                        @click="changePage(page.url)"
                    >
                        <span v-html="page.label" />
                    </Button>

                    <span v-else class="px-1 text-sm text-muted-foreground">
                        …
                    </span>
                </template>
            </div>
        </div>
    </div>
</template>
