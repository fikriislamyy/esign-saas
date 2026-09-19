<script setup>
import { CheckCircle2 } from "lucide-vue-next";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import { Card, CardContent } from "@/components/ui/card";
import LandingSection from "@/Components/landing/LandingSection.vue";

const features = {
    send: {
        title: "Prepare and send in minutes",
        bullets: [
            "Drag-and-drop field placement",
            "Multiple signers with signing order",
            "Personal message to each signer",
            "Reminders for pending signatures",
        ],
    },
    sign: {
        title: "A signing experience people actually finish",
        bullets: [
            "Works on phone, tablet and desktop — no app to install",
            "Email OTP identity verification before signing",
            "Draw, type or upload a signature",
            "Signed PDF delivered to everyone instantly",
        ],
    },
    track: {
        title: "Know the status of every document",
        bullets: [
            "Live dashboard: draft, sent, viewed, signed, completed",
            "Per-signer progress and timestamps",
            "Full audit trail on every document",
            "Filter and search across your whole organization",
        ],
    },
};
</script>

<template>
    <LandingSection muted eyebrow="Everything you need" title="Send. Sign. Track.">
        <Tabs default-value="send" class="w-full">
            <div class="flex justify-center">
                <TabsList class="grid w-full max-w-md grid-cols-3">
                    <TabsTrigger value="send">Send</TabsTrigger>
                    <TabsTrigger value="sign">Sign</TabsTrigger>
                    <TabsTrigger value="track">Track</TabsTrigger>
                </TabsList>
            </div>

            <TabsContent v-for="(content, key) in features" :key="key" :value="key" class="mt-8">
                <div class="grid items-center gap-12 lg:grid-cols-2">
                    <div>
                        <h3 class="mb-4 text-2xl font-bold">{{ content.title }}</h3>
                        <ul class="space-y-3">
                            <li v-for="bullet in content.bullets" :key="bullet" class="flex items-start gap-3">
                                <CheckCircle2 class="h-5 w-5 shrink-0 text-pulse-green" />
                                <span>{{ bullet }}</span>
                            </li>
                        </ul>
                    </div>
                    <Card class="overflow-hidden">
                        <CardContent class="p-0">
                            <img
                                src="/storage/images/dashboard-preview-1.png"
                                alt="Feature preview"
                                class="aspect-[16/10] rounded-xl border object-cover outline outline-1 -outline-offset-1 outline-black/10 dark:outline-white/10"
                            />
                        </CardContent>
                    </Card>
                </div>
            </TabsContent>
        </Tabs>
    </LandingSection>
</template>
