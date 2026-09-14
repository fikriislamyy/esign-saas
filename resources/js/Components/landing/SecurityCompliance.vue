<script setup>
import { KeyRound, ScrollText, ShieldCheck, Lock } from "lucide-vue-next";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import FadeIn from "@/Components/animations/FadeIn.vue";
import LandingSection from "@/Components/landing/LandingSection.vue";

const cards = [
    {
        icon: KeyRound,
        title: "Access & Authentication",
        bullets: [
            "One-time passcode sent to each signer's email",
            "Unique, expiring signing links",
            "Role-based access inside your organization",
            "Session and device protection",
        ],
    },
    {
        icon: ScrollText,
        title: "Compliance & Audit",
        bullets: [
            "Tamper-evident signed PDFs",
            "Timestamped audit trail for every action",
            "Encrypted storage of all documents",
            "Legally binding electronic signatures",
        ],
    },
];

const badges = ["256-bit encryption", "Audit trail", "OTP verified", "GDPR ready"];
</script>

<template>
    <LandingSection id="security" eyebrow="Security" title="Built for trust" subtitle="Every signature is verified, encrypted and auditable.">
        <div class="grid gap-6 md:grid-cols-2">
            <FadeIn v-for="card in cards" :key="card.title" type="scale">
                <Card>
                    <CardHeader>
                        <component :is="card.icon" class="mb-2 h-8 w-8 text-primary" />
                        <CardTitle>{{ card.title }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-2">
                            <li v-for="bullet in card.bullets" :key="bullet" class="flex items-start gap-2 text-sm text-muted-foreground">
                                <ShieldCheck class="h-4 w-4 shrink-0 text-primary" />
                                {{ bullet }}
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </FadeIn>
        </div>

        <div class="mt-12 flex flex-wrap justify-center gap-3">
            <Badge v-for="badge in badges" :key="badge" variant="outline">
                {{ badge }}
            </Badge>
        </div>
    </LandingSection>
</template>
