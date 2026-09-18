<script setup>
import { computed, defineModel, nextTick, onBeforeUnmount, ref, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { loadStripe } from "@stripe/stripe-js";
import { CheckCircle2, CreditCard, Loader2, QrCode, ShieldCheck, TriangleAlert } from "lucide-vue-next";

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
import {
    Tabs,
    TabsList,
    TabsTrigger,
} from "@/components/ui/tabs";

const page = usePage();

const open = defineModel("open", { type: Boolean, default: false });
const processing = ref(false);
const error = ref("");
const amount = ref("");
const method = ref("card");
const cardMount = ref(null);
const cardReady = ref(false);
const status = ref("form");
const resultMessage = ref("");

let stripe = null;
let cardElement = null;

const props = defineProps({
    mode: {
        type: String,
        default: "topup",
    },
    planKey: {
        type: String,
        default: null,
    },
});

const numericAmount = computed(() => {
    const value = Number(amount.value);
    return Number.isFinite(value) && value > 0 ? value : 0;
});

const plan = computed(() => {
    if (props.mode === "plan" && props.planKey) {
        return page.props.plans?.[props.planKey] ?? null;
    }
    return page.props.plans?.pro ?? null;
});

const formattedPrice = computed(() => {
    if (props.mode === "plan" && plan.value) {
        const cents = plan.value.price_usd_cents ?? 1000;
        return new Intl.NumberFormat("en-US", {
            style: "currency",
            currency: "USD",
            minimumFractionDigits: 0,
        }).format(cents / 100);
    }
    return null;
});

const formattedAmount = computed(() =>
    new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: "USD",
    }).format(numericAmount.value),
);

const dialogTitle = computed(() => {
    if (status.value === "success") {
        return "Payment successful";
    }

    if (status.value === "error") {
        return "Payment failed";
    }

    return props.mode === "topup"
        ? "Top Up Wallet"
        : `Upgrade to ${plan.value?.label ?? "Pro"}`;
});

const dialogDescription = computed(() => {
    if (status.value !== "form") {
        return resultMessage.value;
    }

    return props.mode === "topup"
        ? "Add funds to your wallet."
        : "Charge to your card.";
});

// Stripe's style validator rejects space-separated hsl(), which is how the
// theme tokens are stored. Let the browser resolve them to rgb() instead.
function resolveToken(name, fallback) {
    const raw = getComputedStyle(document.documentElement)
        .getPropertyValue(name)
        .trim();

    if (!raw) {
        return fallback;
    }

    const probe = document.createElement("span");
    probe.style.color = /^(#|rgb|hsl)/.test(raw) ? raw : `hsl(${raw})`;
    probe.style.display = "none";

    document.body.appendChild(probe);
    const resolved = getComputedStyle(probe).color;
    probe.remove();

    return resolved || fallback;
}

function elementStyles() {
    const destructive = resolveToken("--destructive", "rgb(220, 38, 38)");

    return {
        base: {
            color: resolveToken("--card-foreground", "rgb(10, 10, 10)"),
            fontFamily:
                'Inter Variable, ui-sans-serif, system-ui, -apple-system, sans-serif',
            fontSize: "14px",
            fontSmoothing: "antialiased",
            "::placeholder": {
                color: resolveToken("--muted-foreground", "rgb(115, 115, 115)"),
            },
        },

        invalid: {
            color: destructive,
            iconColor: destructive,
        },
    };
}

async function mountCard() {
    if (!stripe) {
        stripe = await loadStripe(page.props.stripeKey);
    }

    await nextTick();

    if (!stripe || !cardMount.value) {
        return;
    }

    cardElement = stripe.elements().create("card", {
        style: elementStyles(),
        hidePostalCode: true,
    });

    cardElement.on("change", (event) => {
        error.value = event.error?.message ?? "";
    });

    cardElement.on("ready", () => {
        cardReady.value = true;
    });

    cardElement.mount(cardMount.value);
}

function unmountCard() {
    cardElement?.destroy();
    cardElement = null;
    cardReady.value = false;
}

watch(open, (isOpen) => {
    if (isOpen) {
        error.value = "";
        amount.value = "";
        method.value = "card";
        status.value = "form";
        resultMessage.value = "";
        mountCard();
        return;
    }

    // The webhook credits the wallet a beat after Stripe confirms, so pull
    // fresh balances once the user is done reading the receipt.
    if (status.value === "success" && props.mode === "topup") {
        router.reload({ only: ["wallet", "stats", "transactions"] });
    }

    processing.value = false;
    unmountCard();
});

onBeforeUnmount(unmountCard);

function fail(message) {
    resultMessage.value = message ?? "Something went wrong. Please try again.";
    status.value = "error";
    processing.value = false;
}

function succeed(message) {
    resultMessage.value = message;
    status.value = "success";
    processing.value = false;
}

async function submit() {
    if (processing.value) {
        return;
    }

    processing.value = true;
    error.value = "";

    try {
        if (props.mode === "topup") {
            await submitTopup();
        } else {
            await submitPlan();
        }
    } catch (requestError) {
        fail(requestError.response?.data?.message);
    }
}

async function submitTopup() {
    const { data } = await window.axios.post(route("billing.topups.store"), {
        currency: "USD",
        amount: numericAmount.value,
    });

    const result = await stripe.confirmCardPayment(data.clientSecret, {
        payment_method: { card: cardElement },
    });

    if (result.error) {
        fail(result.error.message);
        return;
    }

    // The webhook does the crediting, so the balance may lag by a moment.
    succeed(
        `We received ${formattedAmount.value}. Your balance updates as soon as Stripe confirms.`,
    );
}

async function submitPlan() {
    const { data } = await window.axios.post(route("plan.checkout"));

    const result = await stripe.confirmCardSetup(data.clientSecret, {
        payment_method: { card: cardElement },
    });

    if (result.error) {
        fail(result.error.message);
        return;
    }

    router.post(
        route("plan.subscribe"),
        {
            plan: props.planKey,
            payment_method: result.setupIntent.payment_method,
        },
        {
            preserveScroll: true,

            // Without this Inertia re-keys the page component, tearing down
            // this dialog before the result can be shown.
            preserveState: true,

            onError: (errors) => {
                fail(Object.values(errors)[0]);
            },

            onSuccess: () => {
                succeed(
                    `You are now on the ${plan.value?.label ?? "Pro"} plan. It renews automatically each month.`,
                );
            },
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger v-if="$slots.trigger" as-child>
            <slot name="trigger" />
        </DialogTrigger>

        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ dialogTitle }}</DialogTitle>

                <DialogDescription>{{ dialogDescription }}</DialogDescription>
            </DialogHeader>

            <!-- Result -->
            <div
                v-if="status !== 'form'"
                class="flex flex-col items-center justify-center py-8"
            >
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-2xl"
                    :class="
                        status === 'success'
                            ? 'bg-primary/10 text-accent-ink'
                            : 'bg-destructive/10 text-destructive'
                    "
                >
                    <CheckCircle2 v-if="status === 'success'" class="h-7 w-7" />

                    <TriangleAlert v-else class="h-7 w-7" />
                </div>
            </div>

            <div v-show="status === 'form'" class="space-y-6 py-2">
                <!-- Tabs -->
                <Tabs v-model="method" class="w-full">
                    <TabsList class="grid w-full grid-cols-2">
                        <TabsTrigger value="card">Card</TabsTrigger>
                        <TabsTrigger value="qris">QRIS</TabsTrigger>
                    </TabsList>
                </Tabs>

                <!-- Card Tab -->
                <div v-show="method === 'card'" class="space-y-4">
                    <!-- Amount (topup mode only) -->
                    <div v-if="mode === 'topup'" class="space-y-2">
                        <Label for="topup-amount">Amount</Label>

                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-muted-foreground">
                                $
                            </span>

                            <Input
                                id="topup-amount"
                                v-model="amount"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="10.00"
                                class="pl-8"
                                :disabled="processing"
                            />
                        </div>
                    </div>

                    <!-- Fixed Price (plan mode only) -->
                    <div v-else class="rounded-lg border bg-card p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs text-muted-foreground">Plan</p>
                                <p class="mt-1 text-lg font-semibold">{{ plan?.label }}</p>
                            </div>

                            <div class="text-right">
                                <p class="text-xs text-muted-foreground">Price</p>
                                <p class="mt-1 text-lg font-semibold">{{ formattedPrice }}/month</p>
                            </div>
                        </div>
                    </div>

                    <!-- Card field -->
                    <div class="space-y-2">
                        <Label for="card-element">Card details</Label>

                        <div class="relative">
                            <div
                                id="card-element"
                                ref="cardMount"
                                class="min-h-[46px] rounded-md border bg-transparent px-3 py-3 transition-colors focus-within:border-ring"
                            />

                            <div
                                v-if="!cardReady"
                                class="absolute inset-0 flex items-center justify-center gap-2 rounded-md border bg-muted/30"
                            >
                                <Loader2 class="h-4 w-4 animate-spin text-muted-foreground" />

                                <span class="text-xs text-muted-foreground">
                                    Loading secure card field...
                                </span>
                            </div>
                        </div>

                        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
                    </div>

                    <!-- Security notice -->
                    <div class="flex gap-3 rounded-lg border bg-muted/30 p-3">
                        <ShieldCheck class="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground" />

                        <div class="space-y-1">
                            <p class="text-xs font-medium">Card details never touch our servers</p>
                            <p class="text-xs leading-5 text-muted-foreground">
                                The field above is hosted by Stripe.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- QRIS Tab (placeholder) -->
                <div v-show="method === 'qris'" class="flex min-h-[200px] flex-col items-center justify-center rounded-lg border bg-muted/30 p-6 text-center">
                    <QrCode class="h-8 w-8 text-muted-foreground" />

                    <p class="mt-3 text-sm font-medium">QRIS is not available yet</p>

                    <p class="mt-1 max-w-xs text-xs leading-5 text-muted-foreground">
                        TODO: wire this tab to the Pakasir QRIS flow. For now, please pay by card.
                    </p>
                </div>
            </div>

            <DialogFooter>
                <template v-if="status === 'success'">
                    <Button type="button" @click="open = false">Done</Button>
                </template>

                <template v-else-if="status === 'error'">
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                    >
                        Close
                    </Button>

                    <Button type="button" @click="status = 'form'">
                        Try again
                    </Button>
                </template>

                <template v-else>
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
                        :disabled="processing || method === 'qris' || !cardReady"
                        @click="submit"
                    >
                        <Loader2 v-if="processing" class="h-4 w-4 animate-spin" />

                        <CreditCard v-else class="h-4 w-4" />

                        {{ processing ? "Processing..." : "Continue" }}
                    </Button>
                </template>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
