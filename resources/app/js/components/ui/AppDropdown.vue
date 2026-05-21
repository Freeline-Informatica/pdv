<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    placement: {
        type: String,
        default: 'bottom-end',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const open = ref(false);
const root = ref(null);

function handleClickOutside(event) {
    if (!root.value) return;
    if (!root.value.contains(event.target)) {
        open.value = false;
    }
}

onMounted(() => {
    window.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    window.removeEventListener('click', handleClickOutside);
});

const panelPlacementClass = computed(() => {
    if (props.placement === 'right-end') {
        return 'left-full bottom-0 ml-2';
    }

    if (props.placement === 'right-start') {
        return 'left-full top-0 ml-2';
    }

    if (props.placement === 'bottom-start') {
        return 'left-0 mt-2';
    }

    return 'right-0 mt-2';
});
</script>

<template>
    <div ref="root" class="relative inline-block text-left w-full">
        <div @click="!props.disabled && (open = !open)">
            <slot name="trigger" />
        </div>
        <div
            v-if="open"
            class="absolute z-[var(--z-dropdown)] min-w-44 rounded-[var(--radius-sm)] border border-[var(--color-border)] bg-[var(--color-bg-surface)] p-2 shadow-[var(--shadow-md)]"
            :class="panelPlacementClass"
        >
            <slot />
        </div>
    </div>
</template>
