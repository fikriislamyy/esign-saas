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
        bullets: ["3 documents / week", "Up to 3 members", "Email OTP verification", "Signed PDF download"],
        button: { text: "Get started", variant: "outline", href: "/register" },
    },
    {
        name: "Pro",
        price: "$10",
        description: "For small teams sending contracts regularly",
        popular: true,
        bullets: ["100 documents / month", "Up to 10 members", "Templates & reminders", "Full audit trail"],
        button: { text: "Subscribe", variant: "default", href: "/plan" },
    },
    {
        name: "Enterprise",
        price: "Custom",
        description: "Custom limits and terms",
        bullets: ["Unlimited documents", "Unlimited members", "Priority support", "Custom branding"],
        button: { text: "Contact sales", variant: "outline", href: "mailto:sales@bebem.my.id" },
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
