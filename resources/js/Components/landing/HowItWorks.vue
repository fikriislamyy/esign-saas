<script setup>
import { Upload, Send, FileCheck2 } from "lucide-vue-next";
import FadeIn from "@/Components/animations/FadeIn.vue";
import LandingSection from "@/Components/landing/LandingSection.vue";

const steps = [
    {
        number: 1,
        icon: Upload,
        title: "Upload your PDF",
        description: "Drag and drop any PDF. Place signature, date and text fields exactly where you need them.",
    },
    {
        number: 2,
        icon: Send,
        title: "Add signers & send",
        description: "Enter signer emails and choose the signing order. Everyone gets a secure link in seconds.",
    },
    {
        number: 3,
        icon: FileCheck2,
        title: "Verify & sign",
        description: "Signers confirm their identity with a one-time code, sign on any device, and everyone receives the completed PDF.",
    },
];
</script>

<template>
    <LandingSection id="how-it-works" eyebrow="How it works" title="Sign a document in three steps">
        <div class="grid gap-8 sm:grid-cols-3">
            <FadeIn v-for="(step, index) in steps" :key="step.number" type="slide" direction="up" :delay="index * 150">
                <div class="text-center">
                    <div class="mb-6 flex justify-center">
                        <div class="flex h-20 w-20 items-center justify-center rounded-full bg-primary/10 text-accent-ink">
                            <span class="text-2xl font-bold">{{ step.number }}</span>
                        </div>
                    </div>
                    <component :is="step.icon" class="mx-auto mb-4 h-8 w-8 text-accent-ink" />
                    <h3 class="mb-2 text-lg font-semibold">{{ step.title }}</h3>
                    <p class="text-muted-foreground">{{ step.description }}</p>
                </div>
            </FadeIn>
        </div>
    </LandingSection>
</template>
