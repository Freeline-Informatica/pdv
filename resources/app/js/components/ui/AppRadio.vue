<script setup>
defineProps({
    modelValue: {
        type: [String, Number, Boolean],
        default: '',
    },
    value: {
        type: [String, Number, Boolean],
        required: true,
    },
    label: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <label class="ui-checkbox-wrap">
        <input
            type="radio"
            class="ui-checkbox"
            :checked="modelValue === value"
            @change="emit('update:modelValue', value)"
        >
        <span>{{ label }}</span>
    </label>
</template>
