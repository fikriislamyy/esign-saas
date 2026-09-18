<script setup>
import { computed, ref } from "vue";
import { Head, router } from "@inertiajs/vue3";
import {
    CreditCard,
    FileText,
    HardDrive,
    Mail,
    QrCode,
    ReceiptText,
    Sparkles,
    Users,
    Zap,
} from "lucide-vue-next";

import AppLayout from "@/Layouts/AppLayout.vue";

import PageHeader from "@/Components/page/PageHeader.vue";
import PageSection from "@/Components/page/PageSection.vue";
import FadeIn from "@/Components/animations/FadeIn.vue";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";

import CardPaymentDialog from "@/Components/plan/CardPaymentDialog.vue";
import QrPaymentDialog from "@/Components/plan/QrPaymentDialog.vue";

const props = defineProps({
    subscription: {
        type: Object,
        required: true,
    },

    usage: {
        type: Object,
        required: true,
    },

    plans: {
        type: Object,
        required: true,
    },

    card: {
        type: Object,
        default: null,
    },

    payments: {
        type: Array,
        default: () => [],
    },
});

const downgrading = ref(false);

const planConfig = computed(() => props.plans?.[props.subscription.plan]);

const statusVariant = computed(() => {
    const map = {
        active: "success",
        past_due: "destructive",
        cancelled: "secondary",
    };

    return map[props.subscription.status] ?? "secondary";
});

const statusLabel = computed(() => {
    const map = {
        active: "Active",
        past_due: "Past due",
        cancelled: "Cancelled",
    };

    return map[props.subscription.status] ?? props.subscription.status;
});

const quotas = computed(() => [
    {
        key: "documents",
        label: "Documents",
        icon: FileText,
        used: props.usage.documents.used,
        limit: props.usage.documents.limit,
        formatter: formatCount,
        caption:
            props.usage.documents.period === "week"
                ? "This week"
                : "This month",
    },

    {
        key: "members",
        label: "Members",
        icon: Users,
        used: props.usage.members.used,
        limit: props.usage.members.limit,
        formatter: formatCount,
        caption: "Includes pending invitations",
    },

    {
        key: "storage",
        label: "Storage",
        icon: HardDrive,
        used: props.usage.storage.used,
        limit: props.usage.storage.limit,
        formatter: formatBytes,
        caption: "Across all uploaded documents",
    },
]);

function percentOf(used, limit) {
    if (limit === null || limit === undefined || limit <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((Number(used) / Number(limit)) * 100));
}

function formatCount(value) {
    return new Intl.NumberFormat("en-US").format(Number(value ?? 0));
}

function formatBytes(bytes) {
    const value = Number(bytes ?? 0);

    if (value <= 0) {
        return "0 MB";
    }

    const units = ["B", "KB", "MB", "GB", "TB"];
    const index = Math.min(
        units.length - 1,
        Math.floor(Math.log(value) / Math.log(1024)),
    );

    const scaled = value / Math.pow(1024, index);

    return `${new Intl.NumberFormat("en-US", {
        maximumFractionDigits: scaled < 10 && index > 1 ? 1 : 0,
    }).format(scaled)} ${units[index]}`;
}

function formatUsd(cents) {
    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: "USD",
        minimumFractionDigits: 2,
    }).format(Number(cents ?? 0) / 100);
}

function formatIdr(amount) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(amount ?? 0));
}

function formatDate(value) {
    if (!value) {
        return "—";
    }

    return new Date(value).toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
}

function paymentVariant(status) {
    const map = {
        paid: "success",
        pending: "pending",
        failed: "destructive",
        expired: "secondary",
    };

    return map[status] ?? "secondary";
}

function contactSales() {
    const email = props.plans?.enterprise?.contact_email;

    window.location.href = `mailto:${email}?subject=Enterprise plan enquiry`;
}

function downgrade() {
    const confirmed = window.confirm(
        "Downgrade to Free? Your Pro subscription will be cancelled and Free plan limits apply immediately.",
    );

    if (!confirmed) {
        return;
    }

    downgrading.value = true;

    router.post(
        route("plan.downgrade"),
        {},
        {
            preserveScroll: true,

            onFinish: () => {
                downgrading.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Plan" />

    <AppLayout>
        <div class="space-y-8">
            <FadeIn :delay="100" type="fade">
                <PageHeader
                    title="Plan"
                    description="Manage your subscription, track usage against your limits, and review payments."
                    :icon="Zap"
                />
            </FadeIn>

            <!-- Current plan -->
            <FadeIn :delay="200" type="scale">
                <Card class="overflow-hidden border-border/60 shadow-sm">
                    <div
                        class="flex flex-col gap-6 p-6 md:p-8 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="space-y-4">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-accent-ink"
                                >
                                    <Sparkles class="h-5 w-5" />
                                </div>

                                <div>
                                    <p class="text-sm font-medium">
                                        Current plan
                                    </p>

                                    <p class="text-xs text-muted-foreground">
                                        Applies to your whole organization
                                    </p>
                                </div>
                            </div>

                            <div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <span
                                        class="text-3xl font-bold tracking-tight sm:text-4xl"
                                    >
                                        {{ planConfig?.label ?? "Free" }}
                                    </span>

                                    <Badge :variant="statusVariant">
                                        {{ statusLabel }}
                                    </Badge>
                                </div>

                                <p
                                    v-if="planConfig?.description"
                                    class="mt-2 text-sm text-muted-foreground"
                                >
                                    {{ planConfig.description }}
                                </p>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex w-full flex-col gap-2 sm:w-auto">
                            <template v-if="subscription.plan === 'free'">
                                <CardPaymentDialog>
                                    <template #trigger>
                                        <Button class="w-full gap-2 sm:min-w-48">
                                            <CreditCard class="h-4 w-4" />
                                            Upgrade to Pro
                                        </Button>
                                    </template>
                                </CardPaymentDialog>

                                <QrPaymentDialog>
                                    <template #trigger>
                                        <Button
                                            variant="outline"
                                            class="w-full gap-2 sm:min-w-48"
                                        >
                                            <QrCode class="h-4 w-4" />
                                            Pay with QRIS
                                        </Button>
                                    </template>
                                </QrPaymentDialog>
                            </template>

                            <template v-else-if="subscription.plan === 'pro'">
                                <Button
                                    variant="outline"
                                    class="w-full gap-2 sm:min-w-48"
                                    @click="contactSales"
                                >
                                    <Mail class="h-4 w-4" />
                                    Contact sales
                                </Button>

                                <Button
                                    variant="ghost"
                                    class="w-full sm:min-w-48"
                                    :disabled="downgrading"
                                    @click="downgrade"
                                >
                                    {{
                                        downgrading
                                            ? "Downgrading..."
                                            : "Downgrade to Free"
                                    }}
                                </Button>
                            </template>

                            <template v-else>
                                <Button
                                    variant="outline"
                                    class="w-full gap-2 sm:min-w-48"
                                    @click="contactSales"
                                >
                                    <Mail class="h-4 w-4" />
                                    Contact sales
                                </Button>
                            </template>
                        </div>
                    </div>

                    <!-- Renewal strip -->
                    <div class="border-t bg-muted/30 px-6 py-4 md:px-8">
                        <div
                            class="flex flex-col gap-1 text-xs sm:flex-row sm:items-center sm:justify-between"
                        >
                            <span class="text-muted-foreground">
                                <template v-if="subscription.plan === 'free'">
                                    Free plan — never expires
                                </template>

                                <template v-else-if="subscription.autoRenews">
                                    Renews automatically via card
                                </template>

                                <template v-else>
                                    One-off QRIS payment — renew manually before
                                    it expires
                                </template>
                            </span>

                            <span class="font-medium text-foreground">
                                <template v-if="subscription.expiredAt">
                                    {{
                                        subscription.autoRenews
                                            ? "Next charge"
                                            : "Expires"
                                    }}
                                    {{ formatDate(subscription.expiredAt) }}
                                </template>

                                <template v-else> No expiry </template>
                            </span>
                        </div>
                    </div>
                </Card>
            </FadeIn>

            <!-- Usage -->
            <FadeIn :delay="300" type="scale">
                <PageSection
                    title="Usage"
                    description="How much of your plan you have used in the current period."
                >
                    <div class="grid gap-6 sm:grid-cols-3">
                        <div
                            v-for="quota in quotas"
                            :key="quota.key"
                            class="space-y-3"
                        >
                            <div class="flex items-center gap-2">
                                <component
                                    :is="quota.icon"
                                    class="h-4 w-4 text-muted-foreground"
                                />

                                <span class="text-sm font-medium">
                                    {{ quota.label }}
                                </span>
                            </div>

                            <div class="space-y-2">
                                <div class="flex items-baseline justify-between">
                                    <span class="text-2xl font-semibold tracking-tight">
                                        {{ quota.formatter(quota.used) }}
                                    </span>

                                    <span class="text-xs text-muted-foreground">
                                        <template v-if="quota.limit === null">
                                            Unlimited
                                        </template>

                                        <template v-else>
                                            of {{ quota.formatter(quota.limit) }}
                                        </template>
                                    </span>
                                </div>

                                <Progress
                                    v-if="quota.limit !== null"
                                    :model-value="
                                        percentOf(quota.used, quota.limit)
                                    "
                                />

                                <p class="text-xs text-muted-foreground">
                                    {{ quota.caption }}
                                </p>
                            </div>
                        </div>
                    </div>
                </PageSection>
            </FadeIn>

            <!-- Payment method -->
            <FadeIn v-if="card" :delay="400" type="scale">
                <PageSection
                    title="Payment Method"
                    description="The card charged when your subscription renews."
                >
                    <div
                        class="flex items-center gap-4 rounded-xl border bg-muted/30 p-4"
                    >
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-xl border bg-card"
                        >
                            <CreditCard class="h-5 w-5 text-muted-foreground" />
                        </div>

                        <div class="min-w-0">
                            <p class="text-sm font-medium capitalize">
                                {{ card.card_brand }} ending
                                {{ card.card_last_four }}
                            </p>

                            <p class="text-xs text-muted-foreground">
                                Expires
                                {{ String(card.card_exp_month).padStart(2, "0") }}/{{
                                    card.card_exp_year
                                }}
                            </p>
                        </div>
                    </div>
                </PageSection>
            </FadeIn>

            <!-- Payment history -->
            <FadeIn :delay="500" type="scale">
                <PageSection
                    title="Payment History"
                    description="Subscription charges across both payment methods."
                    :padding="false"
                >
                    <template v-if="payments.length">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Plan</TableHead>
                                    <TableHead>Method</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead class="text-right">
                                        Amount
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                <TableRow
                                    v-for="payment in payments"
                                    :key="payment.id"
                                >
                                    <TableCell class="whitespace-nowrap">
                                        {{ formatDate(payment.created_at) }}
                                    </TableCell>

                                    <TableCell class="capitalize">
                                        {{ payment.plan }}
                                    </TableCell>

                                    <TableCell class="capitalize">
                                        {{
                                            payment.provider === "pakasir"
                                                ? "QRIS"
                                                : "Card"
                                        }}
                                    </TableCell>

                                    <TableCell>
                                        <Badge
                                            :variant="
                                                paymentVariant(payment.status)
                                            "
                                            class="capitalize"
                                        >
                                            {{ payment.status }}
                                        </Badge>
                                    </TableCell>

                                    <TableCell
                                        class="whitespace-nowrap text-right font-medium"
                                    >
                                        <template
                                            v-if="payment.currency === 'IDR'"
                                        >
                                            {{ formatIdr(payment.amount) }}
                                        </template>

                                        <template v-else>
                                            {{
                                                formatUsd(
                                                    payment.amount_usd_cents,
                                                )
                                            }}
                                        </template>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </template>

                    <template v-else>
                        <div
                            class="flex min-h-[280px] flex-col items-center justify-center px-6 py-12 text-center"
                        >
                            <div
                                class="flex h-14 w-14 items-center justify-center rounded-2xl border bg-muted/60"
                            >
                                <ReceiptText class="h-6 w-6 text-muted-foreground" />
                            </div>

                            <h3 class="mt-4 text-sm font-semibold">
                                No payments yet
                            </h3>

                            <p
                                class="mt-1 max-w-sm text-sm leading-6 text-muted-foreground"
                            >
                                Subscription charges will appear here once you
                                upgrade to a paid plan.
                            </p>
                        </div>
                    </template>
                </PageSection>
            </FadeIn>
        </div>
    </AppLayout>
</template>
