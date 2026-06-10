<script setup>
import { ref, nextTick } from "vue";
import { Menu } from "lucide-vue-next";

import AppSidebar from "@/Components/AppSidebar.vue";
import MobileSidebar from "@/Components/MobileSidebar.vue";
import ThemeToggle from "@/Components/ThemeToggle.vue";
import UserDropdown from "@/Components/UserDropdown.vue";

import { Button } from "@/components/ui/button";

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
    <div class="min-h-screen bg-background">
        <div class="flex">
            <AppSidebar :collapsed="collapsed" :hide-text="hideSidebarText" />

            <div class="flex-1">
                <header
                    class="h-16 border-b flex items-center justify-between px-4"
                >
                    <div class="flex items-center gap-2">
                        <!-- Mobile -->
                        <div class="md:hidden">
                            <MobileSidebar />
                        </div>

                        <!-- Desktop -->
                        <div class="hidden md:block">
                            <Button
                                variant="ghost"
                                size="icon"
                                @click="toggleSidebar"
                            >
                                <Menu class="h-5 w-5" />
                            </Button>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <ThemeToggle />
                        <UserDropdown />
                    </div>
                </header>

                <main class="p-6">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
