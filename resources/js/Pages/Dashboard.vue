<script setup>
import { Head } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-3xl font-bold">Dashboard</h1>

                <p class="text-muted-foreground">Welcome back.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle> Documents </CardTitle>
                    </CardHeader>

                    <CardContent> 0 </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle> Templates </CardTitle>
                    </CardHeader>

                    <CardContent> 0 </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle> Team Members </CardTitle>
                    </CardHeader>

                    <CardContent> 1 </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
