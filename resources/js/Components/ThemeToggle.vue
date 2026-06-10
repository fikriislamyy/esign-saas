<script setup>
import { useColorMode } from "@vueuse/core";
import { Moon, Sun } from "lucide-vue-next";

import { Button } from "@/components/ui/button";

const mode = useColorMode({
    emitAuto: true,
});

const toggleTheme = () => {
    mode.value = mode.value === "dark" ? "light" : "dark";
};
</script>

<template>
    <Button variant="ghost" size="icon" @click="toggleTheme">
        <Sun v-if="mode === 'light'" class="h-5 w-5" />
        <Moon v-else class="h-5 w-5" />
    </Button>
</template>
