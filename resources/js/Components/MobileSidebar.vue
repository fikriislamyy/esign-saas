<script setup>
import { ref } from "vue";

import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";

import { Button } from "@/components/ui/button";

import { Menu } from "lucide-vue-next";

import AppSidebar from "@/Components/Layout/AppSidebar.vue";

const open = ref(false);
</script>

<template>
    <Sheet v-model:open="open">
        <SheetTrigger as-child>
            <Button variant="ghost" size="icon">
                <Menu class="h-5 w-5" />
            </Button>
        </SheetTrigger>

        <SheetContent side="left" class="w-[300px] p-0">
            <AppSidebar mobile :collapsed="false" :hide-text="false" />
        </SheetContent>
    </Sheet>
</template>
