<script setup>
import { Link } from "@inertiajs/vue3";

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from "@/components/ui/breadcrumb";

defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Breadcrumb v-if="items.length">
        <BreadcrumbList>
            <template v-for="(item, index) in items" :key="index">
                <BreadcrumbItem>
                    <BreadcrumbLink v-if="item.href" as-child>
                        <Link :href="item.href">
                            {{ item.label }}
                        </Link>
                    </BreadcrumbLink>

                    <BreadcrumbPage v-else>
                        {{ item.label }}
                    </BreadcrumbPage>
                </BreadcrumbItem>

                <BreadcrumbSeparator v-if="index < items.length - 1" />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
