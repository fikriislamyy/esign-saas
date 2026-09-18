<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { Head, router } from "@inertiajs/vue3";
import QRCode from "qrcode";
import {
    ArrowLeft,
    CheckCircle2,
    Loader2,
    QrCode as QrCodeIcon,
    TriangleAlert,
} from "lucide-vue-next";

import AppLayout from "@/Layouts/AppLayout.vue";

import PageHeader from "@/Components/page/PageHeader.vue";
import FadeIn from "@/Components/animations/FadeIn.vue";

import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";

const props = defineProps({
    orderId: {
        type: String,
        required: true,
    },

    amountIdr: {
        type: Number,
        required: true,
    },

    qrString: {
        type: String,
        default: null,
    },

    expiredAt: {
        type: String,
        default: null,
    },
});

const canvas = ref(null);
const renderError = ref("");
const paid = ref(false);

let pollTimer = null;

const formattedAmount = computed(() => {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(props.amountIdr);
});

onMounted(async () => {
    if (props.qrString && canvas.value) {
        try {
            // Fixed black-on-white: a QR needs maximum contrast to scan, so it
            // keeps its own colours on the white plate rather than theming.
            await QRCode.toCanvas(canvas.value, props.qrString, {
                width: 240,
                margin: 1,
                color: { dark: "#08090a", light: "#ffffff" },
            });
        } catch {
            renderError.value =
                "The QR code could not be rendered. Please try again.";
        }
    }

    startPolling();
});

onBeforeUnmount(stopPolling);

function startPolling() {
    pollTimer = window.setInterval(checkStatus, 3000);
}

function stopPolling() {
    if (pollTimer) {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }
}

async function checkStatus() {
    try {
        const { data } = await window.axios.get(
            `/api/payments/${props.orderId}/status`,
        );

        if (data.status === "paid") {
            paid.value = true;
            stopPolling();

            router.visit(route("plan.index"));
        }
    } catch {
        // Transient failures are expected while polling; keep waiting.
    }
}
</script>

<template>
    <Head title="Pay with QRIS" />

    <AppLayout>
        <div class="space-y-8">
            <FadeIn :delay="100" type="fade">
                <PageHeader
                    title="Pay with QRIS"
                    description="Scan the code with any Indonesian banking or e-wallet app to activate Pro."
                    :icon="QrCodeIcon"
                >
                    <template #actions>
                        <Button
                            variant="outline"
                            class="gap-2"
                            @click="router.visit(route('plan.index'))"
                        >
                            <ArrowLeft class="h-4 w-4" />
                            Back to Plan
                        </Button>
                    </template>
                </PageHeader>
            </FadeIn>

            <FadeIn :delay="200" type="scale">
                <Card
                    class="mx-auto max-w-md overflow-hidden border-border/60 shadow-sm"
                >
                    <div class="flex flex-col items-center gap-6 p-6 md:p-8">
                        <!-- Amount -->
                        <div class="text-center">
                            <p class="text-xs text-muted-foreground">
                                Amount due
                            </p>

                            <p
                                class="mt-1 text-3xl font-bold tracking-tight sm:text-4xl"
                            >
                                {{ formattedAmount }}
                            </p>
                        </div>

                        <!-- QR plate -->
                        <div
                            v-if="qrString && !renderError"
                            class="rounded-xl border bg-white p-4"
                        >
                            <canvas ref="canvas" />
                        </div>

                        <div
                            v-else
                            class="flex w-full items-start gap-3 rounded-xl border bg-muted/30 p-4"
                        >
                            <TriangleAlert
                                class="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground"
                            />

                            <div class="space-y-1">
                                <p class="text-sm font-medium">
                                    QR code unavailable
                                </p>

                                <p class="text-xs leading-5 text-muted-foreground">
                                    {{
                                        renderError ||
                                        "The payment provider did not return a QR code. Please go back and try again."
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Status -->
                        <div
                            class="flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <template v-if="paid">
                                <CheckCircle2
                                    class="h-4 w-4 text-pulse-green"
                                />

                                <span class="text-pulse-green">
                                    Payment confirmed — redirecting
                                </span>
                            </template>

                            <template v-else>
                                <Loader2 class="h-4 w-4 animate-spin" />

                                <span> Waiting for payment </span>
                            </template>
                        </div>
                    </div>

                    <!-- Renewal notice -->
                    <div class="border-t bg-muted/30 px-6 py-4 md:px-8">
                        <p class="text-xs leading-5 text-muted-foreground">
                            This one-off payment covers 30 days. QRIS cannot
                            charge you again automatically, so you will need to
                            repeat this before it expires to stay on Pro.
                        </p>
                    </div>
                </Card>
            </FadeIn>
        </div>
    </AppLayout>
</template>
