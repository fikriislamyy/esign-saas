<script setup>
import SidebarNavItem from "./SidebarNavItem.vue";

defineProps({
    section: {
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
</script>

<template>
    <div class="space-y-3">
        <!-- Section Title -->

        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <p
                v-if="!hideText"
                class="px-3 text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/70"
            >
                {{ section.title }}
            </p>
        </Transition>

        <!-- Navigation -->

        <div class="space-y-1.5">
            <SidebarNavItem
                v-for="item in section.items"
                :key="item.route"
                :item="item"
                :collapsed="collapsed"
                :hide-text="hideText"
            />
        </div>
    </div>
</template>
