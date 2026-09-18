<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { loadStripe } from "@stripe/stripe-js";
import { CreditCard, Loader2, ShieldCheck } from "lucide-vue-next";

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
import { Label } from "@/components/ui/label";

const page = usePage();

const open = ref(false);
const processing = ref(false);
const error = ref("");
const cardMount = ref(null);

let stripe = null;
let cardElement = null;

const plan = computed(() => page.props.plans?.pro ?? null);

const formattedPrice = computed(() => {
    const cents = plan.value?.price_usd_cents ?? 1000;

    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: "USD",
        minimumFractionDigits: 0,
    }).format(cents / 100);
});

// Elements renders in an iframe, so it cannot inherit the page's CSS. Read the
// resolved theme tokens and hand Stripe matching literal colours instead.
function elementStyles() {
    const styles = getComputedStyle(document.documentElement);
    const token = (name) => `hsl(${styles.getPropertyValue(name).trim()})`;

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
        mountCard();

        return;
    }

    processing.value = false;
    unmountCard();
});

onBeforeUnmount(unmountCard);

async function submit() {
    if (!stripe || !cardElement || processing.value) {
        return;
    }

    processing.value = true;
    error.value = "";

    try {
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
                plan: "pro",
                payment_method: result.setupIntent.payment_method,
            },
            {
                onError: (serverErrors) => {
                    error.value =
                        Object.values(serverErrors)[0] ??
                        "The subscription could not be created.";

                    processing.value = false;
                },

                onSuccess: () => {
                    open.value = false;
                },
            },
        );
    } catch (requestError) {
        error.value =
            requestError.response?.data?.message ??
            "We could not start the subscription. Please try again.";

        processing.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot name="trigger">
                <Button class="gap-2">
                    <CreditCard class="h-4 w-4" />
                    Upgrade to Pro
                </Button>
            </slot>
        </DialogTrigger>

        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle> Upgrade to Pro </DialogTitle>

                <DialogDescription>
                    Pay by card and your plan renews automatically each month.
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
                                Billed monthly
                            </p>

                            <p class="mt-1 text-lg font-semibold">
                                {{ formattedPrice }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Card -->
                <div class="space-y-2">
                    <Label for="plan-card-element"> Card details </Label>

                    <div
                        id="plan-card-element"
                        ref="cardMount"
                        class="rounded-md border bg-transparent px-3 py-3 transition-colors focus-within:border-ring"
                    />

                    <p v-if="error" class="text-sm text-destructive">
                        {{ error }}
                    </p>
                </div>

                <!-- Security notice -->
                <div class="flex gap-3 rounded-xl border bg-muted/30 p-4">
                    <ShieldCheck
                        class="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground"
                    />

                    <div class="space-y-1">
                        <p class="text-sm font-medium">
                            Card details never touch our servers
                        </p>

                        <p class="text-xs leading-5 text-muted-foreground">
                            The field above is hosted by Stripe. We only store
                            the brand, last four digits and expiry.
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
                    :disabled="processing"
                    @click="submit"
                >
                    <Loader2 v-if="processing" class="h-4 w-4 animate-spin" />

                    <CreditCard v-else class="h-4 w-4" />

                    {{ processing ? "Processing..." : "Subscribe" }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
