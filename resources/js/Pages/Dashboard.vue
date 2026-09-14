<script setup>
import { Head } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";

import DashboardFilter from "@/Components/dashboard/DashboardFilter.vue";
import DashboardStats from "@/Components/dashboard/DashboardStats.vue";
import ActivityChart from "@/Components/dashboard/ActivityChart.vue";
import StatusChart from "@/Components/dashboard/StatusChart.vue";
import RecentDocuments from "@/Components/dashboard/RecentDocuments.vue";
import DashboardHeader from "@/Components/dashboard/DashboardHeader.vue";

const props = defineProps({
    stats: Object,
    chart: Array,
    statusChart: Array,
    recentDocuments: Array,

    range: String,
    startDate: String,
    endDate: String,
    summary: Object,
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout
        title="Dashboard"
        subtitle="Overview recent documents and Activity"
    >
        <div class="min-w-0 space-y-10">
            <DashboardHeader />

            <div class="flex min-w-0 justify-end">
                <DashboardFilter
                    :range="range"
                    :start-date="startDate"
                    :end-date="endDate"
                />
            </div>

            <DashboardStats :stats="stats" />

            <div class="grid min-w-0 gap-6 lg:grid-cols-3">
                <ActivityChart
                    class="min-w-0 lg:col-span-2"
                    :data="chart"
                    :range="range"
                    :start-date="startDate"
                    :end-date="endDate"
                    :summary="summary"
                />

                <StatusChart
                    class="min-w-0"
                    :data="statusChart"
                    :stats="stats"
                />
            </div>

            <RecentDocuments class="min-w-0" :documents="recentDocuments" />
        </div>
    </AppLayout>
</template>
