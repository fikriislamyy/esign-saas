<script setup>
import { computed } from "vue";

import {
    FileText,
    FilePenLine,
    SendHorizonal,
    CircleCheckBig,
} from "lucide-vue-next";

import { Card, CardContent } from "@/components/ui/card";

import FadeIn from "@/Components/animations/FadeIn.vue";

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },
});

const total = computed(() => props.stats.total || 1);

const cards = computed(() => [
    {
        title: "Total Documents",
        value: props.stats.total,
        subtitle: "This period",
        icon: FileText,
        color: "text-muted-foreground",
        bg: "bg-muted",
    },

    {
        title: "Draft",
        value: props.stats.draft,
        subtitle: "Awaiting Action",
        icon: FilePenLine,
        color: "text-signal-teal",
        bg: "bg-signal-teal/15",
    },

    {
        title: "Sent",
        value: props.stats.sent,
        subtitle: "Waiting for signature",
        icon: SendHorizonal,
        color: "text-iris-violet",
        bg: "bg-iris-violet/15",
    },

    {
        title: "Completed",
        value: props.stats.completed,
        subtitle: "Successfully signed",
        icon: CircleCheckBig,
        color: "text-pulse-green",
        bg: "bg-pulse-green/15",
    },
]);
</script>

<template>
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <FadeIn
            v-for="(card, index) in cards"
            :key="card.title"
            :delay="200 + index * 120"
            :once="false"
            direction="up"
            type="scale"
        >
            <Card
                class="transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
            >
                <CardContent class="p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <p
                                class="text-sm font-medium text-muted-foreground"
                            >
                                {{ card.title }}
                            </p>

                            <p class="mt-4 text-5xl font-bold tracking-tight">
                                {{ card.value }}
                            </p>

                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ card.subtitle }}
                            </p>
                        </div>

                        <div
                            :class="[
                                'flex h-12 w-12 items-center justify-center rounded-xl',
                                card.bg,
                            ]"
                        >
                            <component
                                :is="card.icon"
                                class="h-6 w-6"
                                :class="card.color"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </FadeIn>
    </div>
</template>
