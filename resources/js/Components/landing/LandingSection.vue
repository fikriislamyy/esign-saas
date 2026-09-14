<script setup>
import FadeIn from "@/Components/animations/FadeIn.vue";

defineProps({
    id: { type: String, default: undefined },
    eyebrow: { type: String, default: "" },
    title: { type: String, required: true },
    subtitle: { type: String, default: "" },
    muted: { type: Boolean, default: false },
});
</script>

<template>
    <section :id="id" :class="muted ? 'bg-muted/40' : ''">
        <div class="mx-auto max-w-7xl px-4 py-24 lg:px-8">
            <FadeIn type="fade" direction="up">
                <div class="mx-auto mb-16 max-w-2xl text-center">
                    <p v-if="eyebrow" class="mb-3 text-sm font-semibold uppercase tracking-wider text-primary">
                        {{ eyebrow }}
                    </p>
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">
                        {{ title }}
                    </h2>
                    <p v-if="subtitle" class="mt-4 text-lg text-muted-foreground">
                        {{ subtitle }}
                    </p>
                </div>
            </FadeIn>
            <slot />
        </div>
    </section>
</template>
