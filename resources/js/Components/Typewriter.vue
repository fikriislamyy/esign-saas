<script setup>
import { ref, onMounted } from "vue";

const props = defineProps({
    text: String,
    speed: {
        type: Number,
        default: 40,
    },
});

const display = ref("");

onMounted(() => {
    let i = 0;

    const interval = setInterval(() => {
        display.value += props.text[i];

        i++;

        if (i >= props.text.length) {
            clearInterval(interval);
        }
    }, props.speed);
});
</script>

<template>
    <span>
        {{ display }}
        <span class="animate-pulse">|</span>
    </span>
</template>
