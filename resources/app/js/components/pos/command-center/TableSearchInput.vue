<script setup>
import AppSearchField from '../../ui/AppSearchField.vue';

defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    placeholder: {
        type: String,
        default: 'Buscar mesa, comanda, item ou garçom',
    },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <AppSearchField
        :model-value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        @update:model-value="emit('update:modelValue', $event)"
    />
</template>
