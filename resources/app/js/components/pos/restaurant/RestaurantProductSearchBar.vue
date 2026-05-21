<script setup>
import { Search, X } from 'lucide-vue-next';

defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Buscar item',
    },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <label class="restaurant-search ui-card">
        <Search class="h-4 w-4 text-muted" aria-hidden="true" />
        <input
            :value="modelValue"
            :placeholder="placeholder"
            class="restaurant-search__field"
            type="text"
            @input="emit('update:modelValue', $event.target.value)"
        >
        <button v-if="modelValue" type="button" class="restaurant-search__clear" @click="emit('update:modelValue', '')">
            <X class="h-4 w-4" aria-hidden="true" />
            Limpar
        </button>
    </label>
</template>

<style scoped>
.restaurant-search {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.58rem 0.68rem;
}

.restaurant-search__field {
    flex: 1;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--color-text);
    font-size: 0.94rem;
}

.restaurant-search__field::placeholder {
    color: var(--color-text-muted);
}

.restaurant-search__clear {
    border: 0;
    background: color-mix(in srgb, var(--color-bg-elevated) 85%, var(--color-bg-surface));
    color: var(--color-text-muted);
    border-radius: 0.5rem;
    padding: 0.34rem 0.46rem;
    font-weight: 700;
    font-size: 0.72rem;
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    cursor: pointer;
}
</style>
