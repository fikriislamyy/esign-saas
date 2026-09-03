<script setup>
import { Building2, UserCircle2 } from "lucide-vue-next";

defineProps({
    user: Object,

    organization: Object,

    collapsed: Boolean,

    hideText: Boolean,

    mobile: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="border-t border-border/50 p-3">
        <div
            :class="[
                'flex items-center rounded-xl bg-muted/40 transition-all',
                collapsed && !mobile ? 'justify-center p-2' : 'gap-3 p-3',
            ]"
        >
            <!-- Avatar -->

            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10"
            >
                <UserCircle2 class="h-6 w-6 text-primary" />
            </div>

            <!-- User -->

            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0 translate-x-2"
                enter-to-class="opacity-100 translate-x-0"
                leave-active-class="transition duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="!hideText" class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold">
                        {{ user?.name }}
                    </p>

                    <div
                        class="mt-1 flex items-center gap-1 text-xs text-muted-foreground"
                    >
                        <Building2 class="h-3 w-3" />

                        <span class="truncate">
                            {{ organization?.name }}
                        </span>
                    </div>
                </div>
            </Transition>
        </div>
    </div>
</template>
