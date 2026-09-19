<script setup>
import { nextTick, ref } from "vue";

import AppHeader from "@/Components/Layout/AppHeader.vue";
import AppSidebar from "@/Components/Layout/AppSidebar.vue";

const props = defineProps({
    title: {
        type: String,
        default: "",
    },

    subtitle: {
        type: String,
        default: "",
    },

    breadcrumbs: {
        type: Array,
        default: () => [],
    },
});

const collapsed = ref(false);
const hideSidebarText = ref(false);

function toggleSidebar() {
    if (!collapsed.value) {
        collapsed.value = true;

        setTimeout(() => {
            hideSidebarText.value = true;
        }, 300);
    } else {
        hideSidebarText.value = false;

        nextTick(() => {
            collapsed.value = false;
        });
    }
}
</script>

<template>
    <div class="bg-background">

        <div class="flex min-h-screen">
            <AppSidebar :collapsed="collapsed" :hide-text="hideSidebarText" />

            <div class="flex min-w-0 flex-1 flex-col">
                <AppHeader
                    :title="title"
                    :subtitle="subtitle"
                    :breadcrumbs="breadcrumbs"
                    :collapsed="collapsed"
                    @toggle-sidebar="toggleSidebar"
                />

                <main id="main-content" class="flex-1 overflow-y-auto bg-muted/20">
                    <div class="mx-auto w-full max-w-7xl p-4 lg:p-8">
                        <slot />
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>
