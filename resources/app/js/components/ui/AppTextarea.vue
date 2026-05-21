<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    label: {
        type: String,
        default: '',
    },
    hint: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);
const classes = computed(() => ['ui-field', props.error && 'ui-field-error']);
</script>

<template>
    <label class="ui-field-wrap">
        <span v-if="label" class="ui-label">{{ label }}</span>
        <textarea
            v-bind="$attrs"
            :value="modelValue"
            :class="classes"
            @input="emit('update:modelValue', $event.target.value)"
        />
        <span v-if="error" class="ui-field-error-text">{{ error }}</span>
        <span v-else-if="hint" class="ui-field-hint">{{ hint }}</span>
    </label>
</template>
