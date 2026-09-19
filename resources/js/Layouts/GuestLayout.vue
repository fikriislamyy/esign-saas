<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-muted">
        <a
            href="#main-content"
            class="absolute -top-8 left-4 z-50 rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground focus-visible:top-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
        >
            Skip to main content
        </a>

        <div>
            <Link href="/">
                <ApplicationLogo class="w-20 h-20 fill-current text-muted-foreground" />
            </Link>
        </div>

        <div
            id="main-content"
            class="w-full sm:max-w-md mt-6 px-6 py-4 bg-card shadow-md overflow-hidden sm:rounded-lg"
        >
            <slot />
        </div>
    </div>
</template>
