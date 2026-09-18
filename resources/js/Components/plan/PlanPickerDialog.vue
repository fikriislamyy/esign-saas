<script setup>
import { computed, defineModel } from "vue";
import { usePage, router } from "@inertiajs/vue3";
import { Check, Mail } from "lucide-vue-next";

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";

const page = usePage();
const open = defineModel("open", { type: Boolean, default: false });

const props = defineProps({
    currentPlan: {
        type: String,
        default: "free",
    },
});

const emit = defineEmits(["select"]);

const plans = computed(() => page.props.plans ?? {});

function formatPrice(cents) {
    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: "USD",
        minimumFractionDigits: 0,
    }).format(cents / 100);
}

function formatBytes(bytes) {
    if (bytes <= 0) return "0 MB";

    const units = ["B", "KB", "MB", "GB", "TB"];
    const index = Math.min(
        units.length - 1,
        Math.floor(Math.log(bytes) / Math.log(1024))
    );

    const scaled = bytes / Math.pow(1024, index);

    return `${new Intl.NumberFormat("en-US", {
        maximumFractionDigits: scaled < 10 && index > 1 ? 1 : 0,
    }).format(scaled)} ${units[index]}`;
}

function benefitDocuments(plan) {
    const { limit, period } = plan.limits.documents;
    return limit === null
        ? "Unlimited documents"
        : `${limit} documents per ${period}`;
}

function benefitMembers(plan) {
    return plan.limits.members === null
        ? "Unlimited members"
        : `${plan.limits.members} team members`;
}

function benefitStorage(plan) {
    return plan.limits.storage_bytes === null
        ? "Unlimited storage"
        : `${formatBytes(plan.limits.storage_bytes)} storage`;
}

function choose(key) {
    if (key === "free") {
        const confirmed = window.confirm(
            "Are you sure you want to downgrade to the Free plan? Your current limits will apply immediately."
        );
        if (!confirmed) return;

        router.post(route("plan.downgrade"));
        return;
    }

    if (key === "enterprise") {
        const contactEmail = plans.value.enterprise?.contact_email;
        if (contactEmail) {
            window.location.href = `mailto:${contactEmail}?subject=Enterprise plan enquiry`;
        }
        return;
    }

    // Pro plan: emit select event
    emit("select", key);
}

function buttonLabel(key) {
    if (key === props.currentPlan) {
        return "Current plan";
    }

    if (key === "free") return "Downgrade";
    if (key === "pro") return "Upgrade to Pro";
    return "Contact sales";
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger v-if="$slots.trigger" as-child>
            <slot name="trigger" />
        </DialogTrigger>

        <DialogContent class="sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>Upgrade Plan</DialogTitle>
                <DialogDescription>Choose the plan that fits your needs.</DialogDescription>
            </DialogHeader>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 py-4">
                <div
                    v-for="(plan, key) in plans"
                    :key="key"
                    class="flex flex-col rounded-lg border bg-card p-5"
                    :class="key === currentPlan ? 'border-primary' : ''"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-medium">{{ plan.label }}</span>
                        <Badge v-if="key === currentPlan" variant="secondary">
                            Current
                        </Badge>
                    </div>

                    <!-- Price -->
                    <div class="mt-3">
                        <span class="text-3xl font-bold tracking-tight">
                            {{ formatPrice(plan.price_usd_cents) }}
                        </span>

                        <span v-if="plan.price_usd_cents > 0" class="text-sm text-muted-foreground">
                            /month
                        </span>
                    </div>

                    <!-- Description -->
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ plan.description }}
                    </p>

                    <!-- Benefits -->
                    <ul class="mt-4 space-y-2 text-sm">
                        <li class="flex items-center gap-2">
                            <Check class="h-4 w-4 text-accent-ink" />
                            {{ benefitDocuments(plan) }}
                        </li>

                        <li class="flex items-center gap-2">
                            <Check class="h-4 w-4 text-accent-ink" />
                            {{ benefitMembers(plan) }}
                        </li>

                        <li class="flex items-center gap-2">
                            <Check class="h-4 w-4 text-accent-ink" />
                            {{ benefitStorage(plan) }}
                        </li>
                    </ul>

                    <!-- Button (bottom-aligned) -->
                    <div class="mt-auto pt-5">
                        <Button
                            class="w-full"
                            :variant="key === 'pro' ? 'default' : 'outline'"
                            :disabled="key === currentPlan"
                            @click="choose(key)"
                        >
                            {{ buttonLabel(key) }}
                        </Button>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
