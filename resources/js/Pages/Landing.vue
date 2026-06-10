<script setup>
import { Link } from "@inertiajs/vue3";

import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from "@/components/ui/card";
</script>

<template>
    <div
        class="min-h-screen bg-background flex items-center justify-center px-6"
    >
        <Card class="max-w-xl w-full text-center">
            <CardHeader>
                <CardTitle class="text-5xl"> ESign SaaS </CardTitle>

                <CardDescription>
                    Secure Digital Signing Platform
                </CardDescription>
            </CardHeader>

            <CardContent class="flex justify-center gap-4">
                <Link href="/login">
                    <Button> Login </Button>
                </Link>

                <Link href="/register">
                    <Button variant="outline"> Register </Button>
                </Link>
            </CardContent>
        </Card>
    </div>
</template>
