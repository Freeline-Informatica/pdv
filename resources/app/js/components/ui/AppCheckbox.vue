<script setup>
defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    disabled: {
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
    <label class="ui-checkbox-wrap">
        <input
            type="checkbox"
            class="ui-checkbox"
            :checked="modelValue"
            :disabled="disabled"
            @change="emit('update:modelValue', $event.target.checked)"
        >
        <span>{{ label }}</span>
    </label>
</template>
