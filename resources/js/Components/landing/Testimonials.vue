<script setup>
import { Star } from "lucide-vue-next";
import { Card, CardContent } from "@/components/ui/card";
import FadeIn from "@/Components/animations/FadeIn.vue";
import LandingSection from "@/Components/landing/LandingSection.vue";

const testimonials = [
    {
        quote: "We replaced a three-day paper process with a ten-minute email. Our clients love it.",
        author: "Amira S.",
        role: "Operations Lead",
    },
    {
        quote: "The OTP step gives us confidence that the right person actually signed.",
        author: "Daniel K.",
        role: "Legal Counsel",
    },
    {
        quote: "Simple pricing and a dashboard that tells me exactly what is still waiting.",
        author: "Putri R.",
        role: "HR Manager",
    },
];
</script>

<template>
    <LandingSection eyebrow="Testimonials" title="Loved by teams that sign a lot">
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <FadeIn v-for="testimonial in testimonials" :key="testimonial.author" type="fade">
                <Card>
                    <CardContent class="pt-6">
                        <div class="mb-4 flex gap-1">
                            <Star v-for="i in 5" :key="i" class="h-4 w-4 fill-amber-400 text-amber-400" />
                        </div>
                        <p class="mb-4 text-muted-foreground">
                            "{{ testimonial.quote }}"
                        </p>
                        <p class="font-semibold">{{ testimonial.author }}</p>
                        <p class="text-sm text-muted-foreground">{{ testimonial.role }}</p>
                    </CardContent>
                </Card>
            </FadeIn>
        </div>
    </LandingSection>
</template>
