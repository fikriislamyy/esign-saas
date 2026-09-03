<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import { parseDate } from "@internationalized/date";

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";

import { RangeCalendar } from "@/components/ui/range-calendar";
import { Button } from "@/components/ui/button";
import FadeIn from "@/Components/animations/FadeIn.vue";

const props = defineProps({
    range: {
        type: String,
        default: "week",
    },

    startDate: String,

    endDate: String,
});

const selectedRange = ref(props.range);

const showCalendar = ref(props.range === "custom");

const customRange = ref({
    start: props.startDate ? parseDate(props.startDate) : undefined,

    end: props.endDate ? parseDate(props.endDate) : undefined,
});

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
</script>

<template>
    <FadeIn :delay="100" direction="left" type="fade">
        <div class="flex items-start justify-between">
            <div class="flex items-center gap-3">
                <Popover v-model:open="showCalendar">
                    <PopoverTrigger as-child>
                        <div>
                            <Select
                                :model-value="selectedRange"
                                @update:model-value="changeRange"
                            >
                                <SelectTrigger class="w-[180px]">
                                    <SelectValue />
                                </SelectTrigger>

                                <SelectContent>
                                    <SelectItem value="today">
                                        Today
                                    </SelectItem>

                                    <SelectItem value="week">
                                        This Week
                                    </SelectItem>

                                    <SelectItem value="month">
                                        This Month
                                    </SelectItem>

                                    <SelectItem value="year">
                                        This Year
                                    </SelectItem>

                                    <SelectItem value="custom">
                                        Custom Range...
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </PopoverTrigger>

                    <PopoverContent align="start" class="w-auto p-4">
                        <div class="space-y-4">
                            <RangeCalendar
                                v-model="customRange"
                                :number-of-months="2"
                            />

                            <div class="flex justify-end">
                                <Button @click="applyCustomRange">
                                    Apply
                                </Button>
                            </div>
                        </div>
                    </PopoverContent>
                </Popover>
            </div>
        </div>
    </FadeIn>
</template>
