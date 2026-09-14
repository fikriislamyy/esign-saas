<script setup>
import { ref, onMounted, computed } from "vue";

const props = defineProps({
    delay: {
        type: Number,
        default: 0,
    },

    duration: {
        type: Number,
        default: 800,
    },

    direction: {
        type: String,
        default: "right",
    },

    distance: {
        type: Number,
        default: 50,
    },
});

const visible = ref(false);

onMounted(() => {
    setTimeout(() => {
        visible.value = true;
    }, props.delay);
});

const transform = computed(() => {
    if (visible.value) return "translate(0,0)";

    switch (props.direction) {
        case "left":
            return `translateX(-${props.distance}px)`;

        case "right":
            return `translateX(${props.distance}px)`;

        case "top":
            return `translateY(-${props.distance}px)`;

        default:
            return `translateY(${props.distance}px)`;
    }
});
</script>

<template>
    <div
        :style="{
            transition: `all ${duration}ms ease`,
            opacity: visible ? 1 : 0,
            transform,
        }"
    >
        <slot />
    </div>
</template>
