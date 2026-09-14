<script setup>
import { Head, Link } from "@inertiajs/vue3";

import { ArrowRight, CheckCircle2, FileCheck2 } from "lucide-vue-next";

import { Button } from "@/components/ui/button";

import FadeIn from "@/Components/animations/FadeIn.vue";
</script>

<template>
    <Head title="Signing Completed" />

    <main
        class="flex min-h-screen items-center justify-center bg-background px-6 py-12"
    >
        <FadeIn :delay="100" direction="down" type="scale">
            <div class="w-full max-w-lg">
                <div
                    class="rounded-2xl border bg-card p-8 text-center shadow-xl sm:p-10"
                >
                    <!-- Success Icon -->

                    <div
                        class="mx-auto flex h-20 w-20 items-center justify-center rounded-2xl border bg-green-500/10 text-green-600 dark:text-green-400"
                    >
                        <CheckCircle2 class="h-10 w-10" />
                    </div>

                    <!-- Title -->

                    <h1
                        class="mt-8 text-3xl font-bold tracking-tight sm:text-4xl"
                    >
                        Document Signed
                    </h1>

                    <p
                        class="mx-auto mt-4 max-w-md text-sm leading-6 text-muted-foreground sm:text-base"
                    >
                        Your signature has been submitted successfully. The
                        signing process for this document is now complete for
                        you.
                    </p>

                    <!-- Document Info -->

                    <div
                        class="mt-8 flex items-center gap-4 rounded-xl border bg-muted/30 p-4 text-left"
                    >
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border bg-background"
                        >
                            <FileCheck2 class="h-5 w-5 text-primary" />
                        </div>

                        <div class="min-w-0">
                            <p class="text-sm font-medium">
                                Signing completed successfully
                            </p>

                            <p class="mt-1 text-xs text-muted-foreground">
                                You may close this page.
                            </p>
                        </div>
                    </div>

                    <!-- Action -->

                    <div class="mt-8">
                        <Button as-child class="w-full sm:w-auto">
                            <Link href="/">
                                Return to EZSign

                                <ArrowRight class="ml-2 h-4 w-4" />
                            </Link>
                        </Button>
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
