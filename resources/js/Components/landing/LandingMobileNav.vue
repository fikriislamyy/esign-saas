<script setup>
import { ref } from "vue";
import { Link } from "@inertiajs/vue3";
import { Menu } from "lucide-vue-next";

import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import { Button } from "@/components/ui/button";

const open = ref(false);

const links = [
    { label: "How it works", href: "#how-it-works" },
    { label: "Security", href: "#security" },
    { label: "Pricing", href: "#pricing" },
    { label: "FAQ", href: "#faq" },
];
</script>

<template>
    <Sheet v-model:open="open">
        <SheetTrigger as-child>
            <Button variant="ghost" size="icon" aria-label="Open menu">
                <Menu class="h-5 w-5" />
            </Button>
        </SheetTrigger>

        <SheetContent side="right" class="flex w-[85vw] max-w-[320px] flex-col gap-2 p-6">
            <a
                v-for="link in links"
                :key="link.href"
                :href="link.href"
                class="flex h-11 items-center rounded-lg px-3 text-base hover:bg-muted"
                @click="open = false"
            >
                {{ link.label }}
            </a>

            <div class="mt-4 grid gap-2 border-t pt-4">
                <Button variant="outline" as-child>
                    <Link href="/login">Sign In</Link>
                </Button>

                <Button as-child>
                    <Link href="/register">Get Started</Link>
                </Button>
            </div>
        </SheetContent>
    </Sheet>
</template>
