<script setup>
import { Link, usePage } from "@inertiajs/vue3";

import { LayoutDashboard, FileText, Users, Settings } from "lucide-vue-next";

const page = usePage();

const props = defineProps({
    collapsed: {
        type: Boolean,
        default: false,
    },

    hideText: {
        type: Boolean,
        default: false,
    },
});

const menus = [
    {
        name: "Dashboard",
        href: "/dashboard",
        icon: LayoutDashboard,
    },
    {
        name: "Documents",
        href: "#",
        icon: FileText,
    },
    {
        name: "Members",
        href: "#",
        icon: Users,
    },
    {
        name: "Settings",
        href: "#",
        icon: Settings,
    },
];
</script>

<template>
    <aside
        :class="collapsed ? 'w-16' : 'w-64'"
        class="hidden md:flex flex-col border-r transition-all duration-300"
    >
        <!-- Logo -->
        <div class="h-16 border-b flex items-center justify-center font-bold">
            <Transition
                mode="out-in"
                enter-active-class="transition-opacity duration-200"
                leave-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                leave-to-class="opacity-0"
            >
                <span
                    v-if="!props.collapsed"
                    key="expanded-logo"
                    class="whitespace-nowrap"
                >
                    ESign SaaS
                </span>

                <span v-else key="collapsed-logo" class="whitespace-nowrap">
                    ES
                </span>
            </Transition>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 p-3 space-y-2">
            <Link
                v-for="menu in menus"
                :key="menu.name"
                :href="menu.href"
                :class="[
                    page.url.startsWith(menu.href)
                        ? 'bg-muted font-medium'
                        : 'hover:bg-muted',
                    'flex items-center rounded-lg transition px-3 py-2',
                    props.collapsed ? 'justify-center' : 'gap-3',
                ]"
            >
                <component :is="menu.icon" class="h-5 w-5 shrink-0" />

                <Transition
                    enter-active-class="transition-opacity duration-200"
                    leave-active-class="transition-opacity duration-200"
                    enter-from-class="opacity-0"
                    leave-to-class="opacity-0"
                >
                    <span v-if="!props.collapsed" class="whitespace-nowrap">
                        {{ menu.name }}
                    </span>
                </Transition>
            </Link>
        </nav>
    </aside>
</template>
