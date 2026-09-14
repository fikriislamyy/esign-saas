<script setup>
import { onBeforeUnmount, onMounted, ref } from "vue";
import { router } from "@inertiajs/vue3";
import { parseDate } from "@internationalized/date";

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

import { Popover, PopoverContent } from "@/components/ui/popover";

import { RangeCalendar } from "@/components/ui/range-calendar";
import { Button } from "@/components/ui/button";

import FadeIn from "@/Components/animations/FadeIn.vue";

const props = defineProps({
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
});

const selectedRange = ref(props.range);

const showCalendar = ref(props.range === "custom");

const customRange = ref({
    start: props.startDate ? parseDate(props.startDate) : undefined,

    end: props.endDate ? parseDate(props.endDate) : undefined,
});

/*
|--------------------------------------------------------------------------
| Responsive calendar
|--------------------------------------------------------------------------
*/

const calendarMonths = ref(1);

let mediaQuery = null;

function updateCalendarMonths() {
    if (!mediaQuery) {
        return;
    }

    calendarMonths.value = mediaQuery.matches ? 2 : 1;
}

/*
|--------------------------------------------------------------------------
| Range selection
|--------------------------------------------------------------------------
*/

function changeRange(value) {
    selectedRange.value = value;

    if (value === "custom") {
        showCalendar.value = true;
        return;
    }

    showCalendar.value = false;

    router.get(
        route("dashboard"),
        {
            range: value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function applyCustomRange() {
    if (!customRange.value?.start || !customRange.value?.end) {
        return;
    }

    showCalendar.value = false;

    router.get(
        route("dashboard"),
        {
            range: "custom",
            start_date: customRange.value.start.toString(),
            end_date: customRange.value.end.toString(),
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

/*
|--------------------------------------------------------------------------
| Lifecycle
|--------------------------------------------------------------------------
*/

onMounted(() => {
    mediaQuery = window.matchMedia("(min-width: 768px)");

    updateCalendarMonths();

    mediaQuery.addEventListener("change", updateCalendarMonths);
});

onBeforeUnmount(() => {
    mediaQuery?.removeEventListener("change", updateCalendarMonths);
});
</script>

<template>
    <FadeIn :delay="100" direction="left" type="fade">
        <div class="flex min-w-0 justify-end">
            <!-- Range select -->

            <Select
                :model-value="selectedRange"
                @update:model-value="changeRange"
            >
                <SelectTrigger class="w-[180px]">
                    <SelectValue />
                </SelectTrigger>

                <SelectContent>
                    <SelectItem value="today"> Today </SelectItem>

                    <SelectItem value="week"> This Week </SelectItem>

                    <SelectItem value="month"> This Month </SelectItem>

                    <SelectItem value="year"> This Year </SelectItem>

                    <SelectItem value="custom"> Custom Range... </SelectItem>
                </SelectContent>
            </Select>

            <!-- Custom calendar -->

            <Popover :open="showCalendar" @update:open="showCalendar = $event">
                <PopoverContent
                    align="end"
                    class="w-auto max-w-[calc(100vw-2rem)] p-3 sm:p-4"
                >
                    <div class="space-y-4">
                        <RangeCalendar
                            v-model="customRange"
                            :number-of-months="calendarMonths"
                        />

                        <div class="flex justify-end">
                            <Button
                                :disabled="
                                    !customRange?.start || !customRange?.end
                                "
                                @click="applyCustomRange"
                            >
                                Apply
                            </Button>
                        </div>
                    </div>
                </PopoverContent>
            </Popover>
        </div>
    </FadeIn>
</template>
