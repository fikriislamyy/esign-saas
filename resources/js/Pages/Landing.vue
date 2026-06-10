<script setup>
import { Link } from "@inertiajs/vue3";
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center">
        <h1 class="text-5xl font-bold">ESign SaaS</h1>

        <p class="mt-4 text-gray-500">Secure Digital Signing Platform</p>

        <div class="mt-8 flex gap-4">
            <Link
                href="/login"
                class="px-4 py-2 bg-blue-600 text-white rounded"
            >
                Login
            </Link>

            <Link href="/register" class="px-4 py-2 border rounded">
                Register
            </Link>
        </div>
    </div>
</template>
