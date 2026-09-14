<script setup>
import { onMounted, onUnmounted, ref } from "vue";

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
    const dark = document.documentElement.classList.contains("dark");

    isDarkMode.value = dark;

    dashboardPreviews.value = dark ? darkPreviews : lightPreviews;

    activePreview.value = 0;
}

function nextPreview() {
    activePreview.value =
        (activePreview.value + 1) % dashboardPreviews.value.length;
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

    previewInterval = setInterval(nextPreview, 6000);
});

onUnmounted(() => {
    clearInterval(previewInterval);
    themeObserver?.disconnect();
});
</script>

<template>
    <div
        class="w-full overflow-hidden rounded-2xl border bg-card shadow-xl mt-5"
    >
        <div class="relative overflow-hidden bg-muted/20 p-3 sm:p-4">
            <div
                class="relative aspect-[16/10] overflow-hidden rounded-xl border bg-background"
            >
                <Transition
                    enter-active-class="absolute inset-0 transition-opacity duration-700 ease-in-out"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="absolute inset-0 transition-opacity duration-700 ease-in-out"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <img
                        :key="`${isDarkMode}-${activePreview}`"
                        :src="dashboardPreviews[activePreview]"
                        alt="EZSign dashboard preview"
                        class="absolute inset-0 h-full w-full object-cover"
                    />
                </Transition>

                <!-- Indicators -->

                <div
                    class="absolute bottom-3 left-1/2 z-10 flex -translate-x-1/2 gap-1.5 rounded-full border bg-background/80 px-2 py-1.5 shadow-sm backdrop-blur"
                >
                    <button
                        v-for="(_, index) in dashboardPreviews"
                        :key="index"
                        type="button"
                        class="h-1.5 rounded-full transition-all duration-300"
                        :class="
                            activePreview === index
                                ? 'w-6 bg-primary'
                                : 'w-1.5 bg-muted-foreground/40'
                        "
                        :aria-label="`Show preview ${index + 1}`"
                        @click="activePreview = index"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
