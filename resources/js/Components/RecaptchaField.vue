<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
    modelValue: { type: String, default: "" },
    error: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue"]);

const siteKey = usePage().props.recaptchaSiteKey;
const container = ref(null);
let widgetId = null;

const theme = () =>
    document.documentElement.classList.contains("dark") ? "dark" : "light";

const render = () => {
    if (!siteKey || !window.grecaptcha?.render || !container.value) return;

    widgetId = window.grecaptcha.render(container.value, {
        sitekey: siteKey,
        theme: theme(),
        callback: (token) => emit("update:modelValue", token),
        "expired-callback": () => emit("update:modelValue", ""),
        "error-callback": () => emit("update:modelValue", ""),
    });
};

const reset = () => {
    if (widgetId !== null && window.grecaptcha?.reset) {
        window.grecaptcha.reset(widgetId);
    }
    emit("update:modelValue", "");
};

defineExpose({ reset });

onMounted(() => {
    if (!siteKey) return;

    if (window.grecaptcha?.render) {
        render();
        return;
    }

    const timer = setInterval(() => {
        if (window.grecaptcha?.render) {
            clearInterval(timer);
            render();
        }
    }, 100);

    onBeforeUnmount(() => clearInterval(timer));
});

watch(
    () => props.error,
    (message) => {
        if (message) reset();
    },
);
</script>

<template>
    <div v-if="siteKey" class="space-y-2">
        <div ref="container" class="flex justify-center"></div>

        <p v-if="error" class="text-center text-sm text-destructive">
            {{ error }}
        </p>
    </div>
</template>
