<script setup>
import { Link } from "@inertiajs/vue3";
import { Button } from "@/components/ui/button";
import FadeIn from "@/Components/animations/FadeIn.vue";
</script>

<template>
    <section class="mx-auto max-w-7xl px-4 pb-24 lg:px-8">
        <FadeIn type="scale">
            <div class="rounded-3xl border bg-primary/10 px-8 py-16 text-center">
                <h2 class="text-3xl font-bold sm:text-4xl">
                    Send your first document in under five minutes
                </h2>
                <p class="mt-4 text-lg text-muted-foreground">
                    No credit card required. Your first three documents are free.
                </p>

                <div class="mt-8 flex flex-wrap justify-center gap-4">
                    <Link href="/register">
                        <Button size="lg">Get Started</Button>
                    </Link>
                    <Link href="/login">
                        <Button size="lg" variant="outline">Sign In</Button>
                    </Link>
                </div>
            </div>
        </FadeIn>
    </section>
</template>
