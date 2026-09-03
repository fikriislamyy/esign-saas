<script setup>
import { Head, Link } from "@inertiajs/vue3";
import { onMounted, onUnmounted, ref } from "vue";

import { Button } from "@/components/ui/button";

import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from "@/components/ui/card";

import {
    FileSignature,
    ShieldCheck,
    Zap,
    Users,
    FileCheck2,
    CheckCircle2,
} from "lucide-vue-next";

import Typewriter from "@/Components/Typewriter.vue";
import FadeIn from "@/Components/animations/FadeIn.vue";
import SlideIn from "@/Components/animations/SlideIn.vue";

const activePreview = ref(0);
const isDarkMode = ref(false);

const darkPreviews = [
    "/storage/images/dashboard-preview-1.png",
    "/storage/images/dashboard-preview-2.png",
];

const lightPreviews = [
    "/storage/images/dashboard-preview-3.png",
    "/storage/images/dashboard-preview-4.png",
];

const dashboardPreviews = ref(darkPreviews);

let previewInterval = null;
let themeObserver = null;

function updatePreviewTheme() {
    isDarkMode.value = document.documentElement.classList.contains("dark");

    dashboardPreviews.value = isDarkMode.value ? darkPreviews : lightPreviews;

    // Prevent an invalid index after switching theme.
    activePreview.value = 0;
}

onMounted(() => {
    updatePreviewTheme();

    themeObserver = new MutationObserver(() => {
        updatePreviewTheme();
    });

    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ["class"],
    });

    previewInterval = setInterval(() => {
        activePreview.value =
            (activePreview.value + 1) % dashboardPreviews.value.length;
    }, 6000);
});

onUnmounted(() => {
    clearInterval(previewInterval);
    themeObserver?.disconnect();
});
</script>
<template>
    <Head title="Manage your organization documents" />
    <div class="min-h-screen bg-background">
        <!-- Background Glow -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div
                class="absolute left-1/2 top-24 h-[500px] w-[500px] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl"
            />
        </div>

        <!-- Navbar -->
        <SlideIn direction="down">
            <header
                class="sticky top-0 z-50 border-b bg-background/80 backdrop-blur"
            >
                <div
                    class="mx-auto flex h-16 max-w-7xl items-center justify-between px-6"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10"
                        >
                            <FileSignature class="h-6 w-6 text-primary" />
                        </div>

                        <span class="text-xl font-bold"> EZSign </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <Link href="/login">
                            <Button variant="ghost"> Sign In </Button>
                        </Link>

                        <Link href="/register">
                            <Button> Get Started </Button>
                        </Link>
                    </div>
                </div>
            </header>
        </SlideIn>

        <!-- Hero -->
        <section class="mx-auto max-w-7xl px-6 py-24">
            <div class="grid items-center gap-16 lg:grid-cols-2">
                <!-- Left -->
                <div>
                    <FadeIn type="slide" direction="right">
                        <div
                            class="mb-6 inline-flex items-center gap-2 rounded-full border bg-background px-4 py-2 text-sm"
                        >
                            <CheckCircle2 class="h-4 w-4 text-emerald-500" />

                            Legally Binding Digital Signatures
                        </div>
                    </FadeIn>
                    <FadeIn type="fade">
                        <h1
                            class="text-5xl font-extrabold tracking-tight lg:text-6xl"
                        >
                            <Typewriter
                                text="Secure Digital Signing for Modern Teams"
                            />
                        </h1>
                    </FadeIn>
                    <FadeIn type="fade" :delay="500">
                        <p
                            class="mt-6 max-w-xl text-lg leading-8 text-muted-foreground"
                        >
                            Create, send, sign and manage documents with secure
                            electronic signatures. Collaborate with your team,
                            track document progress, and keep everything
                            organized in one place.
                        </p>
                    </FadeIn>

                    <div class="mt-8 flex flex-wrap gap-4">
                        <FadeIn direction="right" :delay="400">
                            <Link href="/register">
                                <Button size="lg"> Get Started </Button>
                            </Link>
                        </FadeIn>
                        <FadeIn direction="left" :delay="500">
                            <Link href="/login">
                                <Button variant="outline" size="lg">
                                    Sign In
                                </Button>
                            </Link>
                        </FadeIn>
                    </div>

                    <FadeIn type="fade" direction="down" :delay="400">
                        <div
                            class="mt-10 flex flex-wrap gap-6 text-sm text-muted-foreground"
                        >
                            <div class="flex items-center gap-2">
                                <CheckCircle2
                                    class="h-4 w-4 text-emerald-500"
                                />
                                Secure
                            </div>

                            <div class="flex items-center gap-2">
                                <CheckCircle2
                                    class="h-4 w-4 text-emerald-500"
                                />
                                Paperless
                            </div>

                            <div class="flex items-center gap-2">
                                <CheckCircle2
                                    class="h-4 w-4 text-emerald-500"
                                />
                                Team Ready
                            </div>

                            <div class="flex items-center gap-2">
                                <CheckCircle2
                                    class="h-4 w-4 text-emerald-500"
                                />
                                Fast Signing
                            </div>
                        </div>
                    </FadeIn>
                </div>

                <!-- Right -->
                <FadeIn direction="left" type="fade" :delay="600">
                    <div>
                        <Card class="overflow-hidden shadow-2xl">
                            <CardHeader class="border-b bg-muted/40">
                                <CardTitle class="mt-5">
                                    Dashboard Preview
                                </CardTitle>

                                <CardDescription>
                                    Track every document in one place.
                                </CardDescription>
                            </CardHeader>

                            <CardContent class="p-4 sm:p-6">
                                <div
                                    class="relative overflow-hidden rounded-xl border bg-muted/20"
                                >
                                    <div class="relative aspect-[16/9]">
                                        <Transition
                                            enter-active-class="absolute inset-0 transition-opacity duration-700 ease-in-out"
                                            enter-from-class="opacity-0"
                                            enter-to-class="opacity-100"
                                            leave-active-class="absolute inset-0 transition-opacity duration-700 ease-in-out"
                                            leave-from-class="opacity-100"
                                            leave-to-class="opacity-0"
                                        >
                                            <img
                                                :key="activePreview"
                                                :src="
                                                    dashboardPreviews[
                                                        activePreview
                                                    ]
                                                "
                                                alt="EZSign dashboard preview"
                                                class="absolute inset-0 h-full w-full object-cover"
                                            />
                                        </Transition>

                                        <!-- Preview indicators -->

                                        <div
                                            class="absolute bottom-3 left-1/2 z-10 flex -translate-x-1/2 gap-1.5 rounded-full border bg-background/80 px-2 py-1 backdrop-blur"
                                        >
                                            <button
                                                v-for="(
                                                    _, index
                                                ) in dashboardPreviews"
                                                :key="index"
                                                type="button"
                                                class="h-1.5 rounded-full transition-all duration-300"
                                                :class="
                                                    activePreview === index
                                                        ? 'w-6 bg-primary'
                                                        : 'w-1.5 bg-muted-foreground/40'
                                                "
                                                :aria-label="`Show dashboard preview ${index + 1}`"
                                                @click="activePreview = index"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </FadeIn>
            </div>
        </section>

        <!-- Features -->
        <section class="mx-auto max-w-7xl px-6 pb-24">
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                <FadeIn :delay="200" :once="false" type="scale">
                    <Card>
                        <CardHeader>
                            <ShieldCheck class="h-8 w-8 text-primary" />

                            <CardTitle> Secure </CardTitle>
                        </CardHeader>

                        <CardContent>
                            End-to-end encrypted document signing with legally
                            binding signatures.
                        </CardContent>
                    </Card>
                </FadeIn>

                <FadeIn :delay="300" :once="false" type="scale">
                    <Card>
                        <CardHeader>
                            <Zap class="h-8 w-8 text-primary" />

                            <CardTitle> Fast </CardTitle>
                        </CardHeader>

                        <CardContent>
                            Send and sign documents within seconds from
                            anywhere.
                        </CardContent>
                    </Card>
                </FadeIn>

                <FadeIn :delay="400" :once="false" type="scale">
                    <Card>
                        <CardHeader>
                            <Users class="h-8 w-8 text-primary" />

                            <CardTitle> Collaboration </CardTitle>
                        </CardHeader>

                        <CardContent>
                            Invite team members and work together on every
                            document.
                        </CardContent>
                    </Card>
                </FadeIn>

                <FadeIn :delay="500" :once="false" type="scale">
                    <Card>
                        <CardHeader>
                            <FileCheck2 class="h-8 w-8 text-primary" />

                            <CardTitle> Tracking </CardTitle>
                        </CardHeader>

                        <CardContent>
                            Monitor every document status from draft until
                            completion.
                        </CardContent>
                    </Card>
                </FadeIn>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t">
            <div
                class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-6 py-8 text-sm text-muted-foreground md:flex-row"
            >
                <p>© 2026 EZSign. All rights reserved.</p>

                <div class="flex gap-6">
                    <a href="#">Privacy</a>
                    <a href="#">Terms</a>
                    <a href="#">Contact</a>
                </div>
            </div>
        </footer>
    </div>
</template>
