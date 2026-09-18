<script setup>
import { Briefcase, Users, Building2, Scale, GraduationCap, Stethoscope } from "lucide-vue-next";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import FadeIn from "@/Components/animations/FadeIn.vue";
import LandingSection from "@/Components/landing/LandingSection.vue";

const useCases = [
    {
        icon: Briefcase,
        title: "Sales & contracts",
        description: "Close deals faster with proposals and agreements signed the same day.",
    },
    {
        icon: Users,
        title: "HR & onboarding",
        description: "Offer letters, NDAs and policy acknowledgements without printing a page.",
    },
    {
        icon: Building2,
        title: "Real estate",
        description: "Lease agreements and disclosures signed by all parties from anywhere.",
    },
    {
        icon: Scale,
        title: "Legal",
        description: "Engagement letters and settlements with a complete audit trail.",
    },
    {
        icon: GraduationCap,
        title: "Education",
        description: "Enrollment forms and consent slips for parents and students.",
    },
    {
        icon: Stethoscope,
        title: "Healthcare",
        description: "Patient intake and consent forms completed before the visit.",
    },
];
</script>

<template>
    <LandingSection muted eyebrow="Use cases" title="One tool for every agreement">
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <FadeIn v-for="(useCase, index) in useCases" :key="useCase.title" type="scale" :delay="index * 100">
                <Card>
                    <CardHeader>
                        <component :is="useCase.icon" class="mb-2 h-8 w-8 text-accent-ink" />
                        <CardTitle class="text-lg">{{ useCase.title }}</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm text-muted-foreground">
                        {{ useCase.description }}
                    </CardContent>
                </Card>
            </FadeIn>
        </div>
    </LandingSection>
</template>
