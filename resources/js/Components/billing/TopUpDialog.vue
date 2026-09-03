<script setup>
import { computed, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { ArrowRight, CreditCard, Loader2, WalletCards } from "lucide-vue-next";

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
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";

const props = defineProps({
    wallet: {
        type: Object,
        required: true,
    },
});

const open = ref(false);
const currency = ref("USD");
const amount = ref("");
const processing = ref(false);
const errors = ref({});

const usdToIdrRate = computed(() => {
    return Number(props.wallet?.usdToIdrRate || 0);
});

const numericAmount = computed(() => {
    const value = Number(amount.value);

    if (!Number.isFinite(value) || value <= 0) {
        return 0;
    }

    return value;
});

const walletCreditUsd = computed(() => {
    if (!numericAmount.value) {
        return 0;
    }

    if (currency.value === "USD") {
        return numericAmount.value;
    }

    if (!usdToIdrRate.value) {
        return 0;
    }

    return numericAmount.value / usdToIdrRate.value;
});

const formattedWalletCredit = computed(() => {
    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: "USD",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(walletCreditUsd.value || 0);
});

const formattedRate = computed(() => {
    if (!usdToIdrRate.value) {
        return "Unavailable";
    }

    return new Intl.NumberFormat("id-ID", {
        maximumFractionDigits: 2,
    }).format(usdToIdrRate.value);
});

const formattedInputAmount = computed(() => {
    if (!numericAmount.value) {
        return currency.value === "USD" ? "$0.00" : "Rp 0";
    }

    if (currency.value === "USD") {
        return new Intl.NumberFormat("en-US", {
            style: "currency",
            currency: "USD",
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(numericAmount.value);
    }

    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(numericAmount.value);
});

watch(currency, () => {
    amount.value = "";
    errors.value = {};
});

watch(open, (value) => {
    if (!value) {
        amount.value = "";
        errors.value = {};
        processing.value = false;
    }
});

function submit() {
    errors.value = {};

    if (!numericAmount.value) {
        errors.value.amount = "Please enter a valid amount.";
        return;
    }

    if (currency.value === "IDR") {
        if (!Number.isInteger(numericAmount.value)) {
            errors.value.amount = "IDR amount must be a whole number.";
            return;
        }

        if (!usdToIdrRate.value) {
            errors.value.amount =
                "The current USD/IDR exchange rate is unavailable.";
            return;
        }
    }

    processing.value = true;

    router.post(
        route("billing.topups.store"),
        {
            currency: currency.value,
            amount: numericAmount.value,
        },
        {
            preserveScroll: true,

            onError: (serverErrors) => {
                errors.value = serverErrors || {};
            },

            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <!-- Trigger supplied by parent -->
        <DialogTrigger as-child>
            <slot name="trigger">
                <!-- Fallback trigger -->
                <Button class="gap-2">
                    <WalletCards class="h-4 w-4" />
                    Top Up Wallet
                </Button>
            </slot>
        </DialogTrigger>

        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle> Top Up Wallet </DialogTitle>

                <DialogDescription>
                    Add funds to your organization's wallet using Stripe.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-6 py-2">
                <!-- Currency -->
                <div class="space-y-2">
                    <Label> Payment Currency </Label>

                    <Tabs v-model="currency" class="w-full">
                        <TabsList class="grid w-full grid-cols-2">
                            <TabsTrigger value="USD"> USD </TabsTrigger>

                            <TabsTrigger value="IDR"> IDR </TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                <!-- Amount -->
                <div class="space-y-2">
                    <Label for="topup-amount"> Amount </Label>

                    <div class="relative">
                        <span
                            class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-muted-foreground"
                        >
                            {{ currency === "USD" ? "$" : "Rp" }}
                        </span>

                        <Input
                            id="topup-amount"
                            v-model="amount"
                            type="number"
                            min="0"
                            :step="currency === 'USD' ? '0.01' : '1'"
                            :placeholder="
                                currency === 'USD' ? '10.00' : '500000'
                            "
                            class="pl-10 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                            :disabled="processing"
                        />
                    </div>

                    <p v-if="errors.amount" class="text-sm text-destructive">
                        {{ errors.amount }}
                    </p>
                </div>

                <!-- FX -->
                <div
                    v-if="currency === 'IDR'"
                    class="rounded-xl border bg-muted/40 p-4"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium">
                                Current exchange rate
                            </p>

                            <p class="mt-1 text-xs text-muted-foreground">
                                Displayed for estimation. The backend calculates
                                the actual wallet credit when creating the
                                top-up.
                            </p>
                        </div>

                        <div class="text-right">
                            <p
                                v-if="usdToIdrRate"
                                class="text-sm font-semibold"
                            >
                                1 USD ≈ Rp
                                {{ formattedRate }}
                            </p>

                            <p
                                v-else
                                class="text-sm font-medium text-destructive"
                            >
                                Rate unavailable
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Summary -->
                <div class="rounded-2xl border bg-card p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-muted-foreground">You pay</p>

                            <p class="mt-1 text-lg font-semibold">
                                {{ formattedInputAmount }}
                            </p>
                        </div>

                        <ArrowRight class="h-5 w-5 text-muted-foreground" />

                        <div class="text-right">
                            <p class="text-xs text-muted-foreground">
                                Wallet credit
                            </p>

                            <p class="mt-1 text-lg font-semibold">
                                {{ formattedWalletCredit }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Stripe notice -->
                <div class="flex gap-3 rounded-xl border bg-muted/30 p-4">
                    <CreditCard
                        class="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground"
                    />

                    <div class="space-y-1">
                        <p class="text-sm font-medium">
                            Secure Stripe Checkout
                        </p>

                        <p class="text-xs leading-5 text-muted-foreground">
                            You will be redirected to Stripe to complete the
                            payment securely.
                        </p>
                    </div>
                </div>
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
                    :disabled="
                        processing ||
                        !numericAmount ||
                        (currency === 'IDR' && !usdToIdrRate)
                    "
                    @click="submit"
                >
                    <Loader2 v-if="processing" class="h-4 w-4 animate-spin" />

                    <CreditCard v-else class="h-4 w-4" />

                    {{ processing ? "Redirecting..." : "Continue to Payment" }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
