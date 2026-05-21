<script setup>
defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    label: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <label class="inline-flex items-center gap-2">
        <button
            type="button"
            class="relative h-6 w-11 rounded-full border transition"
            :class="modelValue ? 'bg-[var(--color-primary)] border-[var(--color-primary)]' : 'bg-[var(--color-bg-elevated)] border-[var(--color-border-strong)]'"
            @click="emit('update:modelValue', !modelValue)"
        >
            <span
                class="absolute top-0.5 h-4.5 w-4.5 rounded-full bg-[var(--color-bg-surface)] transition"
                :class="modelValue ? 'left-6' : 'left-0.5'"
            />
        </button>
        <span class="text-sm text-main">{{ label }}</span>
    </label>
</template>
