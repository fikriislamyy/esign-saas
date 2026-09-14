<script setup>
import FadeIn from "@/Components/animations/FadeIn.vue";

import { ShieldCheck, FileSignature, Users } from "lucide-vue-next";

import DashboardPreviewCarousel from "@/Components/landing/DashboardPreviewCarousel.vue";
</script>

<template>
    <div
        class="grid min-h-screen bg-gradient-to-br from-background via-background to-muted/40 lg:grid-cols-2"
    >
        <!-- LEFT -->

        <div
            class="hidden flex-col justify-between border-r bg-muted/20 p-14 lg:flex"
        >
            <FadeIn direction="right">
                <div>
                    <!-- Brand -->

                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary font-bold text-primary-foreground"
                        >
                            E
                        </div>

                        <div>
                            <h2 class="text-xl font-bold">EZSign</h2>

                            <p class="text-sm text-muted-foreground">
                                Digital Signature Platform
                            </p>
                        </div>
                    </div>

                    <!-- Hero -->

                    <div class="mt-16">
                        <h1 class="text-5xl font-bold leading-tight">
                            Sign documents
                            <br />

                            <span class="text-primary"> with confidence. </span>
                        </h1>

                        <p class="mt-6 max-w-md text-lg text-muted-foreground">
                            Manage documents, send signatures, collaborate with
                            your team, and track everything from one dashboard.
                        </p>
                    </div>

                    <!-- Benefits -->

                    <div class="mt-10 space-y-5">
                        <div class="flex items-center gap-3">
                            <ShieldCheck class="h-5 w-5 text-emerald-500" />

                            <span> Bank-level security </span>
                        </div>

                        <div class="flex items-center gap-3">
                            <FileSignature class="h-5 w-5 text-primary" />

                            <span> Legally binding signatures </span>
                        </div>

                        <div class="flex items-center gap-3">
                            <Users class="h-5 w-5 text-orange-500" />

                            <span> Built for teams </span>
                        </div>
                    </div>
                </div>
            </FadeIn>

            <!-- Dashboard Preview -->

            <FadeIn :delay="300" direction="right">
                <DashboardPreviewCarousel />
            </FadeIn>
        </div>

        <!-- RIGHT -->

        <div class="flex items-center justify-center p-6 md:p-12">
            <FadeIn :duration="800" direction="left">
                <slot />
            </FadeIn>
        </div>
    </div>
</template>
