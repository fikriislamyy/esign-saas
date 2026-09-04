<script setup>
import { router } from "@inertiajs/vue3";

import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";

import { FileText, Eye, ChevronRight } from "lucide-vue-next";

const props = defineProps({
    documents: {
        type: Array,
        default: () => [],
    },
});

const badgeVariant = (status) => {
    switch (status) {
        case "draft":
            return "secondary";

        case "sent":
            return "default";

        case "completed":
            return "outline";

        default:
            return "secondary";
    }
};

const viewDocument = (id) => {
    router.visit(route("documents.show", id));
};

const viewAll = () => {
    router.visit(route("documents.index"));
};
</script>

<template>
    <Card class="min-w-0 overflow-hidden">
        <CardHeader
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <CardTitle>Recent Documents</CardTitle>

            <Button
                variant="ghost"
                size="sm"
                class="w-fit shrink-0 gap-2"
                @click="viewAll"
            >
                View All

                <ChevronRight class="h-4 w-4" />
            </Button>
        </CardHeader>

        <CardContent class="min-w-0">
            <div v-if="documents.length" class="divide-y">
                <div
                    v-for="document in documents"
                    :key="document.id"
                    class="group flex min-w-0 items-start gap-3 rounded-xl px-3 py-4 transition-all duration-200 hover:bg-muted/40 hover:shadow-sm"
                >
                    <!-- Document -->

                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary transition-transform duration-200 group-hover:scale-105"
                        >
                            <FileText class="h-5 w-5" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate font-medium transition-colors group-hover:text-primary"
                                :title="document.name"
                            >
                                {{ document.name }}
                            </p>

                            <p class="text-sm text-muted-foreground">
                                {{ document.created_at_human }}
                            </p>
                        </div>
                    </div>

                    <!-- Actions -->

                    <div class="flex shrink-0 items-center gap-2">
                        <Badge
                            :variant="badgeVariant(document.status)"
                            class="shrink-0"
                            :class="{
                                'bg-yellow-100 text-yellow-600':
                                    document.status === 'draft',

                                'bg-blue-100 text-blue-600':
                                    document.status === 'sent',

                                'bg-green-100 text-green-700':
                                    document.status === 'completed',
                            }"
                        >
                            {{
                                document.status.charAt(0).toUpperCase() +
                                document.status.slice(1)
                            }}
                        </Badge>

                        <Button
                            variant="ghost"
                            size="icon"
                            class="shrink-0 rounded-full transition-all hover:bg-primary/10 hover:text-primary"
                            @click="viewDocument(document.id)"
                        >
                            <Eye class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Empty state -->

            <div
                v-else
                class="flex flex-col items-center justify-center py-12 text-center"
            >
                <FileText class="h-14 w-14 text-muted-foreground/50" />

                <h3 class="mt-5 font-semibold">No documents yet</h3>

                <p class="mt-2 max-w-xs text-sm text-muted-foreground">
                    Create your first document to start sending agreements for
                    signature.
                </p>
            </div>
        </CardContent>
    </Card>
</template>
