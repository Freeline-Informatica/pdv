<script setup>
import { ref } from 'vue';

defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Buscar produto, código ou código de barras',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'confirm']);
const inputRef = ref(null);

function focus() {
    if (!inputRef.value || inputRef.value.disabled) return;
    inputRef.value.focus();
}

function handleConfirm(event) {
    event.preventDefault();
    emit('confirm');
}

defineExpose({ focus });
</script>

<template>
    <label class="ui-field-wrap">
        <span class="sr-only">Buscar produto</span>
        <input
            ref="inputRef"
            class="ui-field text-base"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            @input="emit('update:modelValue', $event.target.value)"
            @keydown.enter="handleConfirm"
        >
    </label>
</template>
