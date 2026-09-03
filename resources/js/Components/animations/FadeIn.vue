<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from "vue";

const props = defineProps({
    delay: {
        type: Number,
        default: 0,
    },

    duration: {
        type: Number,
        default: 700,
    },

    distance: {
        type: Number,
        default: 24,
    },

    easing: {
        type: String,
        default: "cubic-bezier(0.16, 1, 0.3, 1)",
    },

    once: {
        type: Boolean,
        default: true,
    },

    threshold: {
        type: Number,
        default: 0.15,
    },

    direction: {
        type: String,
        default: "up",
        validator: (v) => ["up", "down", "left", "right"].includes(v),
    },

    type: {
        type: String,
        default: "fade",
        validator: (v) =>
            ["fade", "slide", "scale", "zoom", "blur", "none"].includes(v),
    },
});

const root = ref(null);
const visible = ref(false);

let observer;

const translate = computed(() => {
    switch (props.direction) {
        case "down":
            return `translate3d(0,-${props.distance}px,0)`;

        case "left":
            return `translate3d(${props.distance}px,0,0)`;

        case "right":
            return `translate3d(-${props.distance}px,0,0)`;

        default:
            return `translate3d(0,${props.distance}px,0)`;
    }
});

const hiddenStyle = computed(() => {
    switch (props.type) {
        case "slide":
            return {
                opacity: 0,
                transform: translate.value,
            };

        case "scale":
            return {
                opacity: 0,
                transform: "scale(.95)",
            };

        case "zoom":
            return {
                opacity: 0,
                transform: "scale(.8)",
            };

        case "blur":
            return {
                opacity: 0,
                transform: translate.value,
                filter: "blur(10px)",
            };

        case "none":
            return {
                opacity: 0,
            };

        default:
            return {
                opacity: 0,
                transform: translate.value,
            };
    }
});

const visibleStyle = computed(() => ({
    opacity: 1,
    transform: "translate3d(0,0,0) scale(1)",
    filter: "blur(0px)",
}));

const styleObject = computed(() => ({
    ...(visible.value ? visibleStyle.value : hiddenStyle.value),

    transition: `
        opacity ${props.duration}ms ${props.easing},
        transform ${props.duration}ms ${props.easing},
        filter ${props.duration}ms ${props.easing}
    `,

    willChange: "opacity, transform, filter",
}));

onMounted(() => {
    observer = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    visible.value = true;
                }, props.delay);

                if (props.once) {
                    observer.disconnect();
                }
            } else if (!props.once) {
                visible.value = false;
            }
        },
        {
            threshold: props.threshold,
        },
    );

    if (root.value) {
        observer.observe(root.value);
    }
});

onBeforeUnmount(() => {
    observer?.disconnect();
});
</script>

<template>
    <div ref="root" :style="styleObject">
        <slot />
    </div>
</template>
