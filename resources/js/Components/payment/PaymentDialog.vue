<script setup>
import { computed, defineModel, nextTick, onBeforeUnmount, ref, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { loadStripe } from "@stripe/stripe-js";
import { CreditCard, Loader2, QrCode, ShieldCheck, TriangleAlert } from "lucide-vue-next";

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

function elementStyles() {
    const styles = getComputedStyle(document.documentElement);
    const token = (name) => {
        const value = styles.getPropertyValue(name).trim();
        if (!value) return "#000";
        return value.startsWith("hsl") ? value : `hsl(${value})`;
    };

    return {
        base: {
            color: token("--card-foreground"),
            fontFamily:
                'Inter Variable, ui-sans-serif, system-ui, -apple-system, sans-serif',
            fontSize: "14px",
            fontSmoothing: "antialiased",
            "::placeholder": {
                color: token("--muted-foreground"),
            },
        },

        invalid: {
            color: token("--destructive"),
            iconColor: token("--destructive"),
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

    cardElement.mount(cardMount.value);
}

function unmountCard() {
    cardElement?.destroy();
    cardElement = null;
}

watch(open, (isOpen) => {
    if (isOpen) {
        error.value = "";
        amount.value = "";
        method.value = "card";
        mountCard();
        return;
    }

    processing.value = false;
    unmountCard();
});

onBeforeUnmount(unmountCard);

async function submit() {
    if (processing.value) {
        return;
    }

    processing.value = true;
    error.value = "";

    try {
        if (method.value === "qris") {
            error.value = "QRIS is not available yet.";
            processing.value = false;
            return;
        }

        if (props.mode === "topup") {
            await submitTopup();
        } else {
            await submitPlan();
        }
    } catch (requestError) {
        error.value =
            requestError.response?.data?.message ??
            "Something went wrong. Please try again.";
        processing.value = false;
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
        error.value = result.error.message;
        processing.value = false;
        return;
    }

    // Webhook credits the wallet, not us. Just close and refresh.
    open.value = false;
    router.reload({ only: ["wallet", "stats", "transactions"] });
}

async function submitPlan() {
    const { data } = await window.axios.post(route("plan.checkout"));

    const result = await stripe.confirmCardSetup(data.clientSecret, {
        payment_method: { card: cardElement },
    });

    if (result.error) {
        error.value = result.error.message;
        processing.value = false;
        return;
    }

    router.post(
        route("plan.subscribe"),
        {
            plan: props.planKey,
            payment_method: result.setupIntent.payment_method,
        },
        {
            onError: (errors) => {
                error.value =
                    Object.values(errors)[0] ?? "Subscription failed.";
                processing.value = false;
            },

            onSuccess: () => {
                open.value = false;
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
                <DialogTitle>
                    {{ mode === "topup" ? "Top Up Wallet" : "Upgrade to " + plan?.label }}
                </DialogTitle>

                <DialogDescription>
                    {{ mode === "topup" ? "Add funds to your wallet." : "Charge to your card." }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-6 py-2">
                <!-- Tabs -->
                <Tabs v-model="method" class="w-full">
                    <TabsList class="grid w-full grid-cols-2">
                        <TabsTrigger value="card">Card</TabsTrigger>
                        <TabsTrigger value="qris">QRIS</TabsTrigger>
                    </TabsList>
                </Tabs>

                <!-- Card Tab -->
                <div v-if="method === 'card'" class="space-y-4">
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

                        <div
                            id="card-element"
                            ref="cardMount"
                            class="rounded-md border bg-transparent px-3 py-3 transition-colors focus-within:border-ring"
                        />

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
                <div v-else class="flex min-h-[200px] flex-col items-center justify-center rounded-lg border bg-muted/30 p-6 text-center">
                    <QrCode class="h-8 w-8 text-muted-foreground" />

                    <p class="mt-3 text-sm font-medium">QRIS is not available yet</p>

                    <p class="mt-1 max-w-xs text-xs leading-5 text-muted-foreground">
                        TODO: wire this tab to the Pakasir QRIS flow. For now, please pay by card.
                    </p>
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
                    :disabled="processing || method === 'qris'"
                    @click="submit"
                >
                    <Loader2 v-if="processing" class="h-4 w-4 animate-spin" />

                    <CreditCard v-else class="h-4 w-4" />

                    {{ processing ? "Processing..." : "Continue" }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
