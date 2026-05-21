<script setup>
defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Buscar...',
    },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <label class="ui-field-wrap">
        <span class="sr-only">Busca</span>
        <div class="relative">
            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted">⌕</span>
            <input
                class="ui-field pl-8"
                :value="modelValue"
                :placeholder="placeholder"
                @input="emit('update:modelValue', $event.target.value)"
            >
        </div>
    </label>
</template>
