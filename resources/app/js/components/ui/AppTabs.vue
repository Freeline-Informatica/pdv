<script setup>
defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    items: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <button
            v-for="item in items"
            :key="item.value"
            type="button"
            class="pos-shortcut"
            :class="modelValue === item.value ? '!bg-[var(--color-primary)] !text-[var(--color-text-inverse)] !border-[var(--color-primary)]' : ''"
            @click="emit('update:modelValue', item.value)"
        >
            {{ item.label }}
        </button>
    </div>
</template>
