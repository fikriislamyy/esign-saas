<script setup>
import { computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import { Menu, WalletCards } from "lucide-vue-next";

import MobileSidebar from "@/Components/MobileSidebar.vue";
import ThemeToggle from "@/Components/ThemeToggle.vue";
import UserDropdown from "@/Components/UserDropdown.vue";
import AppBreadcrumb from "@/Components/Layout/AppBreadcrumb.vue";

import { Button } from "@/components/ui/button";

const page = usePage();

const props = defineProps({
    collapsed: {
        type: Boolean,
        default: false,
    },

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

const emit = defineEmits(["toggle-sidebar"]);

const wallet = computed(() => page.props.wallet ?? null);

const walletBalanceUsdCents = computed(() => {
    return Number(wallet.value?.balanceUsdCents ?? 0);
});

const formattedWalletBalance = computed(() => {
    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: "USD",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(walletBalanceUsdCents.value / 100);
});

const formattedWalletIdr = computed(() => {
    const amount = wallet.value?.balanceIdr;

    if (amount === null || amount === undefined) {
        return null;
    }

    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(amount);
});
</script>

<template>
    <header
        class="sticky top-0 z-40 flex h-16 items-center justify-between border-b border-border/60 bg-background/80 px-4 backdrop-blur-xl supports-[backdrop-filter]:bg-background/70 lg:px-8"
    >
        <!-- Left -->
        <div class="flex min-w-0 items-center gap-3">
            <!-- Mobile Sidebar -->
            <div class="md:hidden">
                <MobileSidebar />
            </div>

            <!-- Desktop Sidebar Toggle -->
            <div class="hidden md:block">
                <Button
                    variant="ghost"
                    size="icon"
                    @click="emit('toggle-sidebar')"
                >
                    <Menu class="h-5 w-5" />
                </Button>
            </div>

            <div v-if="title" class="hidden min-w-0 flex-col gap-1 md:flex">
                <AppBreadcrumb :items="breadcrumbs" />

                <h1 class="truncate text-xl font-semibold tracking-tight">
                    {{ title }}
                </h1>

                <p
                    v-if="subtitle"
                    class="truncate text-sm text-muted-foreground"
                >
                    {{ subtitle }}
                </p>
            </div>

            <div class="hidden h-6 w-px bg-border md:block" />
        </div>

        <!-- Right -->
        <div class="flex shrink-0 items-center gap-2">
            <!-- Wallet -->
            <Link
                v-if="wallet"
                :href="route('billing.index')"
                class="group flex items-center gap-2 rounded-xl border border-border/60 bg-background/70 px-2.5 py-2 transition-colors hover:bg-muted/60 sm:px-3"
            >
                <div
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary transition-colors group-hover:bg-primary/15"
                >
                    <WalletCards class="h-4 w-4" />
                </div>

                <div class="hidden leading-tight sm:block">
                    <p class="text-sm font-semibold tabular-nums">
                        {{ formattedWalletBalance }}
                    </p>

                    <p
                        v-if="formattedWalletIdr"
                        class="text-[10px] text-muted-foreground"
                    >
                        ≈ {{ formattedWalletIdr }}
                    </p>
                </div>

                <!-- Mobile -->
                <div class="sm:hidden">
                    <p class="text-xs font-semibold tabular-nums">
                        {{ formattedWalletBalance }}
                    </p>
                </div>
            </Link>

            <ThemeToggle />

            <UserDropdown />
        </div>
    </header>
</template>
