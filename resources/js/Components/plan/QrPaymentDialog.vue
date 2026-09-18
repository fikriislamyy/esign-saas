<script setup>
import { computed, ref, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { Loader2, QrCode, TriangleAlert } from "lucide-vue-next";

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";

import { Button } from "@/components/ui/button";

const page = usePage();

const open = ref(false);
const processing = ref(false);
const error = ref("");

const plan = computed(() => page.props.plans?.pro ?? null);

const usdToIdrRate = computed(() => Number(page.props.wallet?.usdToIdrRate || 0));

const formattedPrice = computed(() => {
    const cents = plan.value?.price_usd_cents ?? 1000;

    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: "USD",
        minimumFractionDigits: 0,
    }).format(cents / 100);
});

const formattedIdr = computed(() => {
    if (!usdToIdrRate.value) {
        return null;
    }

    const cents = plan.value?.price_usd_cents ?? 1000;

    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Math.round((cents / 100) * usdToIdrRate.value));
});

watch(open, (isOpen) => {
    if (!isOpen) {
        processing.value = false;
        error.value = "";
    }
});

function submit() {
    processing.value = true;
    error.value = "";

    router.post(
        route("plan.qr"),
        { plan: "pro" },
        {
            onError: (serverErrors) => {
                error.value =
                    Object.values(serverErrors)[0] ??
                    "The QR code could not be generated.";

                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot name="trigger">
                <Button variant="outline" class="gap-2">
                    <QrCode class="h-4 w-4" />
                    Pay with QRIS
                </Button>
            </slot>
        </DialogTrigger>

        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle> Pay with QRIS </DialogTitle>

                <DialogDescription>
                    Scan a QR code with any Indonesian banking or e-wallet app.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-6 py-2">
                <!-- Summary -->
                <div class="rounded-2xl border bg-card p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-muted-foreground">Plan</p>

                            <p class="mt-1 text-lg font-semibold">
                                {{ plan?.label ?? "Pro" }}
                            </p>
                        </div>

                        <div class="text-right">
                            <p class="text-xs text-muted-foreground">
                                {{ formattedPrice }} for 30 days
                            </p>

                            <p
                                v-if="formattedIdr"
                                class="mt-1 text-lg font-semibold"
                            >
                                {{ formattedIdr }}
                            </p>

                            <p
                                v-else
                                class="mt-1 text-sm font-medium text-destructive"
                            >
                                Rate unavailable
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Manual renewal warning -->
                <div class="flex gap-3 rounded-xl border bg-muted/30 p-4">
                    <TriangleAlert
                        class="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground"
                    />

                    <div class="space-y-1">
                        <p class="text-sm font-medium">
                            QRIS does not renew automatically
                        </p>

                        <p class="text-xs leading-5 text-muted-foreground">
                            This is a one-off payment covering 30 days. You will
                            need to pay again before it expires, or your
                            organization drops back to the Free plan.
                        </p>
                    </div>
                </div>

                <p v-if="error" class="text-sm text-destructive">
                    {{ error }}
                </p>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="processing"
                    @click="open = false"
                >
                    Cancel
                </Button>

                <Button
                    type="button"
                    class="gap-2"
                    :disabled="processing || !usdToIdrRate"
                    @click="submit"
                >
                    <Loader2 v-if="processing" class="h-4 w-4 animate-spin" />

                    <QrCode v-else class="h-4 w-4" />

                    {{ processing ? "Generating..." : "Generate QR code" }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
