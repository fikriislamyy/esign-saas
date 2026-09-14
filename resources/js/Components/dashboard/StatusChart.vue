<script setup>
import { computed, ref } from "vue";

import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from "@/components/ui/card";

import { VisSingleContainer, VisDonut } from "@unovis/vue";

const props = defineProps({
    stats: Object,
});

const activeStatus = ref(null);

const data = computed(() => [
    {
        name: "Draft",
        value: Number(props.stats?.draft ?? 0),
        color: "#eab308",
    },
    {
        name: "Sent",
        value: Number(props.stats?.sent ?? 0),
        color: "#3b82f6",
    },
    {
        name: "Completed",
        value: Number(props.stats?.completed ?? 0),
        color: "#22c55e",
    },
]);

const total = computed(() =>
    data.value.reduce((sum, item) => sum + item.value, 0),
);

function percentage(value) {
    if (!total.value) return 0;

    return Math.round((value / total.value) * 100);
}

const draft = computed(() => props.stats.draft);
const sent = computed(() => props.stats.sent);
const completed = computed(() => props.stats.completed);

const completionRate = computed(() => {
    if (total.value === 0) return 0;

    return Math.round((completed.value / total.value) * 100);
});

const completionColor = computed(() => {
    if (completionRate.value >= 80) return "text-emerald-600";
    if (completionRate.value >= 50) return "text-amber-500";
    return "text-rose-500";
});
</script>

<template>
    <Card class="h-full">
        <CardHeader>
            <CardTitle>Status Distribution</CardTitle>

            <CardDescription> Current document status </CardDescription>
        </CardHeader>

        <CardContent>
            <div class="relative">
                <VisSingleContainer :data="data" class="h-[260px]">
                    <VisDonut
                        :value="(d) => d.value"
                        :color="(d) => d.color"
                        :cornerRadius="6"
                        :padAngle="0.02"
                    />
                </VisSingleContainer>

                <div
                    class="absolute inset-0 flex flex-col items-center justify-center"
                >
                    <span class="text-5xl font-bold leading-none">
                        {{ total }}
                    </span>

                    <span class="mt-1 text-sm text-muted-foreground">
                        Documents
                    </span>

                    <div class="mt-3 h-px w-10 bg-border"></div>

                    <span
                        class="mt-3 text-xl font-semibold"
                        :class="completionColor"
                    >
                        {{ completionRate }}%
                    </span>

                    <span class="text-xs text-muted-foreground">
                        Completed
                    </span>
                </div>
            </div>

            <div class="mt-6 space-y-3">
                <div
                    v-for="item in data"
                    :key="item.name"
                    @mouseenter="activeStatus = item.name"
                    @mouseleave="activeStatus = null"
                    class="relative flex items-center justify-between rounded-xl border p-4 transition-all duration-200 cursor-pointer"
                    :class="[
                        activeStatus === item.name
                            ? 'border-primary shadow-md scale-[1.02]'
                            : activeStatus
                              ? 'opacity-50'
                              : 'opacity-100',
                    ]"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="absolute bottom-0 left-0 h-1 w-full overflow-hidden rounded-b-xl"
                        >
                            <div
                                class="h-full transition-all duration-300"
                                :style="{
                                    width: percentage(item.value) + '%',
                                    background: item.color,
                                }"
                            />
                        </div>
                        <div
                            class="h-3 w-3 rounded-full transition-transform duration-200"
                            :style="{ background: item.color }"
                            :class="
                                activeStatus === item.name ? 'scale-150' : ''
                            "
                        />

                        <span class="font-medium">
                            {{ item.name }}
                        </span>
                    </div>

                    <div class="text-right">
                        <div class="font-semibold text-lg">
                            {{ item.value }}
                        </div>

                        <div class="text-xs text-muted-foreground">
                            {{ percentage(item.value) }}%
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
