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

import { Line, Scatter } from "@unovis/ts";

import { BarChart3 } from "lucide-vue-next";

import FadeIn from "@/Components/animations/FadeIn.vue";

const props = defineProps({
    data: Array,
    range: String,
    startDate: String,
    endDate: String,
    summary: Object,
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

    if (!point) return "";

    switch (props.range) {
        case "today":
            return point.label;

        case "week":
            return point.label;

        case "month":
            return point.label;

        case "year":
            return point.label;

        default:
            return point.label;
    }
}

const highest = computed(() => {
    return Math.max(...chartData.value.map((i) => i.y), 0);
});

const lowest = computed(() => {
    return Math.min(...chartData.value.map((i) => i.y), 0);
});

const average = computed(() => {
    if (!chartData.value.length) return 0;

    return (
        chartData.value.reduce((sum, i) => sum + i.y, 0) /
        chartData.value.length
    ).toFixed(1);
});
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
        <Card class="h-full rounded-2xl shadow-sm">
            <CardHeader class="flex flex-row items-start justify-between">
                <div>
                    <CardTitle>Document Activity</CardTitle>
                    <CardDescription>
                        Activity during selected range
                    </CardDescription>
                </div>

                <div class="text-right">
                    <p class="text-4xl font-bold">
                        {{ totalDocuments }}
                    </p>

                    <p class="text-xs text-muted-foreground">Documents</p>
                </div>
            </CardHeader>

            <CardContent>
                <div
                    v-if="chartData.length === 0"
                    class="h-[360px] flex items-center justify-center text-muted-foreground"
                >
                    <div
                        class="flex h-[360px] flex-col items-center justify-center text-center"
                    >
                        <BarChart3 class="h-10 w-10 text-muted-foreground" />

                        <h3 class="mt-4 font-semibold">No document activity</h3>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Documents created during this period will appear
                            here.
                        </p>
                    </div>
                </div>

                <VisXYContainer
                    v-else
                    :data="chartData"
                    :padding="{ left: 50, right: 20, top: 20, bottom: 40 }"
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

                <div class="mt-6 border-t pt-6">
                    <div class="grid grid-cols-3 gap-6">
                        <div>
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

                        <div>
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

                        <div>
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
