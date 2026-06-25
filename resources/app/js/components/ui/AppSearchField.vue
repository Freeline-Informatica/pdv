<script setup>
import { Search } from 'lucide-vue-next';

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
    <label class="ui-field-wrap app-search-field">
        <span class="sr-only">Busca</span>
        <div class="app-search-field__control">
            <Search class="app-search-field__icon" :size="17" aria-hidden="true" />
            <input
                v-bind="$attrs"
                class="ui-field app-search-field__input"
                :value="modelValue"
                :placeholder="placeholder"
                @input="emit('update:modelValue', $event.target.value)"
            >
        </div>
    </label>
</template>

<style scoped>
.app-search-field__control {
    position: relative;
}

.app-search-field__icon {
    position: absolute;
    left: 0.85rem;
    top: 50%;
    z-index: 1;
    color: var(--color-text-muted);
    pointer-events: none;
    transform: translateY(-50%);
}

.app-search-field__input {
    padding-left: 2.65rem;
}
</style>
