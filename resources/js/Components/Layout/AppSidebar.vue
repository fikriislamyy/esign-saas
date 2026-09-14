<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";

import SidebarBrand from "./SidebarBrand.vue";
import SidebarSection from "./SidebarSection.vue";
import SidebarFooter from "./SidebarFooter.vue";

import { navigation } from "./navigation";

const props = defineProps({
    collapsed: {
        type: Boolean,
        default: false,
    },

    hideText: {
        type: Boolean,
        default: false,
    },

    mobile: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();

const user = computed(() => page.props.auth.user);
const organization = computed(() => page.props.auth.organization);
</script>

<template>
    <component
        :is="mobile ? 'div' : 'aside'"
        :class="[
            mobile
                ? 'flex h-full flex-col bg-card'
                : 'hidden md:flex flex-col border-r border-border/50 bg-card',

            'transition-all duration-300 ease-in-out',

            collapsed ? 'w-[72px]' : 'w-[280px]',
        ]"
    >
        <!-- Brand -->

        <SidebarBrand :collapsed="collapsed" :hide-text="hideText" />

        <!-- Navigation -->

        <div class="flex-1 overflow-y-auto px-3 py-6">
            <div
                v-for="(section, index) in navigation"
                :key="section.title"
                class="space-y-5"
            >
                <!-- Divider -->

                <div v-if="index > 0" class="mx-2 border-t border-border/50" />

                <SidebarSection
                    :section="section"
                    :collapsed="collapsed"
                    :hide-text="hideText"
                />
            </div>
        </div>

        <!-- Footer -->

        <SidebarFooter
            :user="user"
            :organization="organization"
            :collapsed="collapsed"
            :hide-text="hideText"
            :mobile="mobile"
        />
    </component>
</template>
