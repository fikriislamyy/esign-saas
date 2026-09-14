<script setup>
import { computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";

const page = usePage();

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },

    collapsed: {
        type: Boolean,
        default: false,
    },

    hideText: {
        type: Boolean,
        default: false,
    },
});

const isOwner = computed(() => {
    return page.props.auth?.user?.role === "owner";
});

const isVisible = computed(() => {
    if (!props.item.ownerOnly) {
        return true;
    }

    return isOwner.value;
});

const isActive = computed(() => {
    return route().current(props.item.route);
});
</script>

<template>
    <Link
        v-if="isVisible"
        :href="route(item.route)"
        :title="collapsed ? item.title : undefined"
        :class="[
            'group relative flex h-11 items-center rounded-xl transition-all duration-200',

            collapsed ? 'justify-center px-0' : 'gap-3 px-3',

            isActive
                ? 'bg-primary text-primary-foreground shadow-sm'
                : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
        ]"
    >
        <!-- Active Indicator -->

        <div
            v-if="isActive"
            class="absolute bottom-2 left-0 top-2 w-1 rounded-r-full bg-primary-foreground/80"
        />

        <!-- Icon -->

        <component
            :is="item.icon"
            :class="[
                'h-5 w-5 shrink-0 transition-transform duration-200',

                !isActive && 'group-hover:scale-110',
            ]"
        />

        <!-- Text -->

        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="translate-x-2 opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition duration-150"
            leave-from-class="translate-x-0 opacity-100"
            leave-to-class="translate-x-2 opacity-0"
        >
            <span
                v-if="!hideText"
                class="truncate whitespace-nowrap text-sm font-medium"
            >
                {{ item.title }}
            </span>
        </Transition>
    </Link>
</template>
