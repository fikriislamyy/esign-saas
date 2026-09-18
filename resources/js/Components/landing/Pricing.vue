<script setup>
import { Link } from "@inertiajs/vue3";
import { CheckCircle2 } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import FadeIn from "@/Components/animations/FadeIn.vue";
import LandingSection from "@/Components/landing/LandingSection.vue";

const plans = [
    {
        name: "Starter",
        price: "Free",
        description: "For individuals trying it out",
        bullets: ["3 documents / month", "1 user", "Email OTP verification", "Signed PDF download"],
        button: { text: "Get started", variant: "outline", href: "/register" },
    },
    {
        name: "Team",
        price: "Pay as you go",
        description: "Credits for growing teams",
        popular: true,
        bullets: ["Unlimited documents (per-credit)", "Unlimited members", "Templates & reminders", "Full audit trail"],
        button: { text: "Top up credits", variant: "default", href: "/register" },
    },
    {
        name: "Business",
        price: "Contact us",
        description: "Volume pricing & onboarding",
        bullets: ["Volume credit discounts", "Priority support", "Custom branding", "Dedicated onboarding"],
        button: { text: "Talk to us", variant: "outline", href: "mailto:no-reply@bebem.my.id" },
    },
];
</script>

<template>
    <LandingSection id="pricing" muted eyebrow="Pricing" title="Start free. Scale when you're ready." subtitle="Top up credits and pay only for what you sign.">
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <FadeIn v-for="plan in plans" :key="plan.name" type="scale">
                <Card :class="plan.popular && 'border-accent-ink shadow-lg'">
                    <CardHeader>
                        <div class="flex items-start justify-between">
                            <div>
                                <CardTitle>{{ plan.name }}</CardTitle>
                                <CardDescription>{{ plan.description }}</CardDescription>
                            </div>
                            <Badge v-if="plan.popular">Most popular</Badge>
                        </div>
                        <div class="mt-4 text-3xl font-bold">{{ plan.price }}</div>
                    </CardHeader>

                    <CardContent>
                        <ul class="mb-6 space-y-2">
                            <li v-for="bullet in plan.bullets" :key="bullet" class="flex items-start gap-2 text-sm">
                                <CheckCircle2 class="h-4 w-4 shrink-0 text-pulse-green" />
                                {{ bullet }}
                            </li>
                        </ul>

                        <Link :href="plan.button.href" class="w-full">
                            <Button :variant="plan.button.variant" class="w-full">
                                {{ plan.button.text }}
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </FadeIn>
        </div>

        <p class="mt-12 text-center text-sm text-muted-foreground">
            All plans include legally binding signatures and encrypted storage.
        </p>
    </LandingSection>
</template>
