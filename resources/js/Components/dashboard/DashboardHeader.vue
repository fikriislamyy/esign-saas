<script setup>
import { computed } from "vue";
import { CalendarDays } from "lucide-vue-next";
import FadeIn from "@/Components/animations/FadeIn.vue";

const props = defineProps({
    range: String,
});

const today = computed(() => {
    return new Date().toLocaleDateString("en-US", {
        weekday: "long",
        day: "numeric",
        month: "long",
        year: "numeric",
    });
});
</script>

<template>
    <FadeIn :delay="100" type="fade" direction="down">
        <div
            class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between"
        >
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Dashboard</h1>

                <p class="mt-2 text-muted-foreground">
                    Track your documents, signatures and activity.
                </p>
            </div>

            <div
                class="flex items-center gap-2 rounded-xl border bg-card px-4 py-2 shadow-sm"
            >
                <CalendarDays class="h-4 w-4 text-muted-foreground" />

                <span class="text-sm text-muted-foreground">
                    {{ today }}
                </span>
            </div>
        </div>
    </FadeIn>
</template>
