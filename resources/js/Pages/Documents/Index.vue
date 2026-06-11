<script setup>
import { Head } from "@inertiajs/vue3";

import AppLayout from "@/Layouts/AppLayout.vue";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

defineProps({
    documents: Array,
});
</script>

<template>
    <Head title="Documents" />

    <AppLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-3xl font-bold">Documents</h1>

                <p class="text-muted-foreground">
                    Manage organization documents.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle> Documents </CardTitle>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="documents.length === 0"
                        class="text-muted-foreground"
                    >
                        No documents uploaded yet.
                    </div>

                    <div v-else>Coming soon...</div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
