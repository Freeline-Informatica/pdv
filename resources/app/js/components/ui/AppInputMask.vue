<script setup>
import AppInput from './AppInput.vue';

defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    label: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

function onInput(value) {
    emit('update:modelValue', value);
}
</script>

<template>
    <AppInput
        v-bind="$attrs"
        :model-value="modelValue"
        :label="label"
        @update:model-value="onInput"
    />
</template>
