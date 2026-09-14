<script setup>
import { Head } from "@inertiajs/vue3";

import { ArrowLeft, ShieldAlert, ShieldCheck } from "lucide-vue-next";

import { Button } from "@/components/ui/button";

import FadeIn from "@/Components/animations/FadeIn.vue";

const props = defineProps({
    message: {
        type: String,
        default: "You don't have permission to access this page.",
    },
});

function goBack() {
    window.history.back();
}
</script>

<template>
    <Head title="Access Restricted" />

    <main
        class="relative flex min-h-screen items-center justify-center overflow-hidden bg-background px-6 py-12"
    >
        <!-- Background Glow -->

        <div
            class="pointer-events-none absolute left-1/2 top-1/2 h-[500px] w-[500px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-primary/5 blur-3xl"
        />

        <!-- Content -->

        <FadeIn :delay="100" direction="down" type="scale">
            <div class="relative w-full max-w-lg">
                <!-- Card -->

                <div class="rounded-2xl border bg-card p-8 shadow-xl sm:p-10">
                    <!-- Brand -->

                    <div class="mb-8 flex justify-center">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl border bg-muted/50"
                            >
                                <ShieldCheck class="h-6 w-6 text-primary" />
                            </div>

                            <div>
                                <p class="text-lg font-bold tracking-tight">
                                    EZSign
                                </p>

                                <p class="text-xs text-muted-foreground">
                                    Secure Digital Signing
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Error -->

                    <div class="text-center">
                        <div
                            class="mx-auto flex h-20 w-20 items-center justify-center rounded-2xl border border-destructive/20 bg-destructive/10 text-destructive"
                        >
                            <ShieldAlert class="h-10 w-10" />
                        </div>

                        <p
                            class="mt-8 text-sm font-semibold uppercase tracking-[0.2em] text-muted-foreground"
                        >
                            Error 403
                        </p>

                        <h1
                            class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl"
                        >
                            Access Restricted
                        </h1>

                        <!-- Actual server message -->

                        <p
                            class="mx-auto mt-4 max-w-md text-sm leading-6 text-muted-foreground sm:text-base"
                        >
                            {{ props.message }}
                        </p>
                    </div>

                    <!-- Divider -->

                    <div class="my-8 border-t" />

                    <!-- Action -->

                    <div class="flex justify-center">
                        <Button variant="outline" type="button" @click="goBack">
                            <ArrowLeft class="mr-2 h-4 w-4" />

                            Go Back
                        </Button>
                    </div>

                    <!-- Security Notice -->

                    <div
                        class="mt-8 rounded-xl border bg-muted/30 p-4 text-center"
                    >
                        <p class="text-xs leading-5 text-muted-foreground">
                            For security reasons, access to certain documents
                            and signing requests is restricted.
                        </p>
                    </div>
                </div>

                <!-- Footer -->

                <p class="mt-6 text-center text-xs text-muted-foreground">
                    © {{ new Date().getFullYear() }} EZSign. All rights
                    reserved.
                </p>
            </div>
        </FadeIn>
    </main>
</template>
