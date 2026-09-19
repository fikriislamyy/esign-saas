<script setup>
import { computed } from "vue";
import { useColorMode } from "@vueuse/core";
import { Moon, Sun } from "lucide-vue-next";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { withoutTransitions } from "@/lib/theme-transitions";

const mode = useColorMode({
    emitAuto: true,
    initialValue: "dark",
});

const isLight = computed(() => mode.value === "light");

const shown = "scale-100 opacity-100 blur-0";
const hidden = "scale-[0.25] opacity-0 blur-[4px]";
const base = "transition-[opacity,filter,scale] duration-300 ease-[cubic-bezier(0.2,0,0,1)]";

function toggleTheme() {
    withoutTransitions(() => {
        mode.value = isLight.value ? "dark" : "light";
    });
}
</script>

<template>
    <Button variant="ghost" size="icon" @click="toggleTheme">
        <span class="relative flex h-5 w-5 items-center justify-center">
            <!-- Absolute icon overlays; the Moon below defines the layout box. -->
            <Sun :class="cn('absolute inset-0', base, isLight ? shown : hidden)" class="h-5 w-5" />
            <Moon :class="cn(base, isLight ? hidden : shown)" class="h-5 w-5" />
        </span>
    </Button>
</template>
