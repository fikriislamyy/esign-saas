<script setup>
import { computed, ref } from "vue";

import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from "@/components/ui/card";

import {
    VisXYContainer,
    VisLine,
    VisArea,
    VisAxis,
    VisTooltip,
    VisScatter,
} from "@unovis/vue";

import { Scatter } from "@unovis/ts";

import { BarChart3 } from "lucide-vue-next";

import FadeIn from "@/Components/animations/FadeIn.vue";

const props = defineProps({
    data: {
        type: Array,
        default: () => [],
    },

    range: {
        type: String,
        default: "week",
    },

    startDate: {
        type: String,
        default: "",
    },

    endDate: {
        type: String,
        default: "",
    },

    summary: {
        type: Object,
        default: () => ({
            peakLabel: "",
            peakValue: 0,
            peakTotal: 0,
            average: 0,
            total: 0,
        }),
    },
});

const hoveredPointScatter = ref(null);

const chartData = computed(() =>
    props.data.map((item, index) => ({
        x: index,
        label: item.label,
        y: Number(item.total),
    })),
);

const tooltipTriggers = {
    [Scatter.selectors.point]: (d) => `
        <div style="font-size:12px;color:#6B7280">
            ${d.label}
        </div>

        <div style="margin-top:6px;font-weight:600">
            ${d.y} Documents
        </div>
    `,
};

const totalDocuments = computed(() =>
    chartData.value.reduce((sum, item) => sum + item.y, 0),
);

const maxY = computed(() => {
    const max = Math.max(...chartData.value.map((i) => i.y), 1);

    return max + 1;
});

function formatXAxis(index) {
    const point = chartData.value[index];

    if (!point) {
        return "";
    }

    switch (props.range) {
        case "today":
        case "week":
        case "month":
        case "year":
        default:
            return point.label;
    }
}
</script>

<style>
[data-vis-xy-container] g[class*="scatter-component"] path {
    opacity: 0.5;
    transition: opacity 0.2s ease;
}

[data-vis-xy-container] g[class*="scatter-component"] g:hover path {
    opacity: 1;
}
</style>

<template>
    <FadeIn type="scale" :delay="300">
        <Card class="min-w-0 overflow-hidden rounded-2xl shadow-sm">
            <CardHeader
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="min-w-0">
                    <CardTitle>Document Activity</CardTitle>

                    <CardDescription>
                        Activity during selected range
                    </CardDescription>
                </div>

                <div class="shrink-0 text-left sm:text-right">
                    <p class="text-4xl font-bold">
                        {{ totalDocuments }}
                    </p>

                    <p class="text-xs text-muted-foreground">Documents</p>
                </div>
            </CardHeader>

            <CardContent class="min-w-0">
                <!-- Empty state -->

                <div
                    v-if="chartData.length === 0"
                    class="flex h-[300px] w-full items-center justify-center text-muted-foreground sm:h-[360px]"
                >
                    <div
                        class="flex max-w-sm flex-col items-center justify-center text-center"
                    >
                        <BarChart3 class="h-10 w-10 text-muted-foreground" />

                        <h3 class="mt-4 font-semibold">No document activity</h3>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Documents created during this period will appear
                            here.
                        </p>
                    </div>
                </div>

                <!-- Chart -->

                <div
                    v-else
                    class="h-[300px] w-full min-w-0 overflow-hidden sm:h-[360px]"
                >
                    <VisXYContainer
                        :data="chartData"
                        :padding="{
                            left: 45,
                            right: 15,
                            top: 20,
                            bottom: 35,
                        }"
                        style="width: 100%; height: 100%"
                    >
                        <VisAxis type="x" :tick-format="formatXAxis" />

                        <VisAxis type="y" :domain="[0, maxY]" />

                        <VisArea
                            :x="(d) => d.x"
                            :y="(d) => d.y"
                            :duration="500"
                            color="rgba(59,130,246,.14)"
                        />

                        <VisLine
                            :x="(d) => d.x"
                            :y="(d) => d.y"
                            :duration="500"
                            color="#3b82f6"
                            :lineWidth="3"
                        />

                        <VisScatter
                            :x="(d) => d.x"
                            :y="(d) => d.y"
                            color="#3B82F6"
                            :size="12"
                        />

                        <VisTooltip :triggers="tooltipTriggers" />
                    </VisXYContainer>
                </div>

                <!-- Summary -->

                <div class="mt-6 border-t pt-6">
                    <div class="grid min-w-0 grid-cols-1 gap-5 sm:grid-cols-3">
                        <div class="min-w-0">
                            <p
                                class="text-xs uppercase tracking-wide text-muted-foreground"
                            >
                                {{ summary.peakLabel }}
                            </p>

                            <p class="mt-2 text-lg font-semibold">
                                {{ summary.peakValue }}
                            </p>

                            <p class="text-sm text-muted-foreground">
                                {{ summary.peakTotal }} documents
                            </p>
                        </div>

                        <div class="min-w-0">
                            <p
                                class="text-xs uppercase tracking-wide text-muted-foreground"
                            >
                                Average
                            </p>

                            <p class="mt-2 text-lg font-semibold">
                                {{ summary.average }}
                            </p>

                            <p class="text-sm text-muted-foreground">
                                Documents / period
                            </p>
                        </div>

                        <div class="min-w-0">
                            <p
                                class="text-xs uppercase tracking-wide text-muted-foreground"
                            >
                                Total
                            </p>

                            <p class="mt-2 text-lg font-semibold">
                                {{ summary.total }}
                            </p>

                            <p class="text-sm text-muted-foreground">
                                Documents
                            </p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </FadeIn>
</template>
