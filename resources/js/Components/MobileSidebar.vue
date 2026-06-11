<script setup>
import { Link, usePage } from "@inertiajs/vue3";

import { ref, onMounted, onUnmounted } from "vue";

import {
    Menu,
    LayoutDashboard,
    FileText,
    Users,
    Settings,
} from "lucide-vue-next";

import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from "@/components/ui/sheet";

import { Button } from "@/components/ui/button";

const page = usePage();

const open = ref(false);

function handleResize() {
    if (window.innerWidth >= 768) {
        open.value = false;
    }
}

onMounted(() => {
    window.addEventListener("resize", handleResize);
});

onUnmounted(() => {
    window.removeEventListener("resize", handleResize);
});

const menus = [
    {
        name: "Dashboard",
        href: "/dashboard",
        icon: LayoutDashboard,
    },
    {
        name: "Documents",
        href: "/documents",
        icon: FileText,
    },
    {
        name: "Members",
        href: "/members",
        icon: Users,
    },
    {
        name: "Settings",
        href: "/settings/organization",
        icon: Settings,
    },
];
</script>

<template>
    <Sheet v-model:open="open">
        <SheetTrigger as-child>
            <Button variant="ghost" size="icon">
                <Menu class="h-5 w-5" />
            </Button>
        </SheetTrigger>

        <SheetContent side="left" class="w-[280px] sm:w-[320px] p-0">
            <SheetHeader class="border-b px-6 py-4">
                <SheetTitle class="text-left"> ESign SaaS </SheetTitle>
            </SheetHeader>

            <nav class="p-4 space-y-2">
                <Link
                    v-for="menu in menus"
                    :key="menu.name"
                    :href="menu.href"
                    :class="[
                        page.url.startsWith(menu.href)
                            ? 'bg-muted font-medium'
                            : 'hover:bg-muted',
                        'flex items-center gap-3 rounded-lg px-3 py-3 transition',
                    ]"
                >
                    <component :is="menu.icon" class="h-5 w-5 shrink-0" />

                    <span>
                        {{ menu.name }}
                    </span>
                </Link>
            </nav>
        </SheetContent>
    </Sheet>
</template>
