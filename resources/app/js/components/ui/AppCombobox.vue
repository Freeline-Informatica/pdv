<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { ChevronDown } from 'lucide-vue-next';

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    noResultsText: {
        type: String,
        default: 'Nenhuma opção encontrada',
    },
});

const emit = defineEmits(['update:modelValue', 'select']);

const root = ref(null);
const open = ref(false);
const highlightedIndex = ref(-1);

const inputValue = computed(() => String(props.modelValue ?? ''));

const filteredOptions = computed(() => {
    const query = inputValue.value.trim().toLowerCase();
    const values = props.options
        .map((option) => String(option ?? '').trim())
        .filter(Boolean);

    if (!query) return values.slice(0, 10);

    return values
        .filter((option) => option.toLowerCase().includes(query))
        .slice(0, 10);
});

function openList() {
    if (props.disabled) return;
    open.value = true;
    highlightedIndex.value = filteredOptions.value.length ? 0 : -1;
}

function closeList() {
    open.value = false;
    highlightedIndex.value = -1;
}

function handleInput(event) {
    emit('update:modelValue', event.target.value);
    openList();
}

function selectOption(option) {
    emit('update:modelValue', option);
    emit('select', option);
    closeList();
}

function onKeydown(event) {
    if (!open.value && ['ArrowDown', 'ArrowUp'].includes(event.key)) {
        openList();
        event.preventDefault();
        return;
    }

    if (!open.value) return;

    if (event.key === 'ArrowDown') {
        if (filteredOptions.value.length > 0) {
            highlightedIndex.value = Math.min(highlightedIndex.value + 1, filteredOptions.value.length - 1);
        }
        event.preventDefault();
        return;
    }

    if (event.key === 'ArrowUp') {
        if (filteredOptions.value.length > 0) {
            highlightedIndex.value = Math.max(highlightedIndex.value - 1, 0);
        }
        event.preventDefault();
        return;
    }

    if (event.key === 'Enter') {
        if (highlightedIndex.value >= 0 && filteredOptions.value[highlightedIndex.value]) {
            selectOption(filteredOptions.value[highlightedIndex.value]);
            event.preventDefault();
        }
        return;
    }

    if (event.key === 'Escape') {
        closeList();
    }
}

function handleClickOutside(event) {
    if (!root.value) return;
    if (!root.value.contains(event.target)) {
        closeList();
    }
}

onMounted(() => {
    window.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    window.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div ref="root" class="ui-combobox" :class="{ 'is-disabled': disabled }">
        <div class="ui-combobox-input-wrap">
            <input
                :value="inputValue"
                :placeholder="placeholder"
                class="ui-field ui-combobox-input"
                :disabled="disabled"
                autocomplete="off"
                @focus="openList"
                @input="handleInput"
                @keydown="onKeydown"
            >
            <button
                type="button"
                class="ui-combobox-toggle"
                :disabled="disabled"
                aria-label="Abrir opções"
                @click="open ? closeList() : openList()"
            >
                <ChevronDown :size="16" />
            </button>
        </div>

        <div v-if="open" class="ui-combobox-list">
            <button
                v-for="(option, index) in filteredOptions"
                :key="`${option}-${index}`"
                type="button"
                class="ui-combobox-option"
                :class="{ 'is-highlighted': highlightedIndex === index }"
                @mouseenter="highlightedIndex = index"
                @mousedown.prevent
                @click="selectOption(option)"
            >
                {{ option }}
            </button>
            <p v-if="filteredOptions.length === 0" class="ui-combobox-empty">{{ noResultsText }}</p>
        </div>
    </div>
</template>

<style scoped>
.ui-combobox {
    position: relative;
    width: 100%;
}

.ui-combobox-input-wrap {
    position: relative;
}

.ui-combobox-input {
    padding-right: 2.45rem;
}

.ui-combobox-toggle {
    position: absolute;
    right: 0.38rem;
    top: 50%;
    transform: translateY(-50%);
    width: 1.85rem;
    height: 1.85rem;
    border-radius: 0.5rem;
    border: 1px solid color-mix(in srgb, var(--color-border) 82%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 92%, #131827);
    color: var(--color-text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition-fast);
}

.ui-combobox-toggle:hover:not(:disabled) {
    border-color: color-mix(in srgb, var(--color-primary) 46%, transparent);
    color: var(--color-text);
}

.ui-combobox-toggle:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.ui-combobox-list {
    position: absolute;
    z-index: var(--z-dropdown);
    top: calc(100% + 0.35rem);
    left: 0;
    right: 0;
    border-radius: 0.7rem;
    border: 1px solid color-mix(in srgb, var(--color-border) 84%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 96%, #0d1220);
    box-shadow: var(--shadow-md);
    padding: 0.3rem;
    max-height: 14rem;
    overflow-y: auto;
}

.ui-combobox-option {
    width: 100%;
    border: 1px solid transparent;
    border-radius: 0.5rem;
    background: transparent;
    color: var(--color-text);
    text-align: left;
    font-size: 0.92rem;
    font-weight: 600;
    padding: 0.5rem 0.58rem;
    transition: all var(--transition-fast);
}

.ui-combobox-option.is-highlighted,
.ui-combobox-option:hover {
    border-color: color-mix(in srgb, var(--color-primary) 42%, transparent);
    background: color-mix(in srgb, var(--color-primary) 13%, var(--color-bg-surface));
}

.ui-combobox-empty {
    margin: 0;
    padding: 0.58rem;
    color: var(--color-text-muted);
    font-size: 0.84rem;
}
</style>
