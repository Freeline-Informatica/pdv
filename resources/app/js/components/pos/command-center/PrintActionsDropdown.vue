<script setup>
import { Printer } from 'lucide-vue-next';
import AppDropdown from '../../ui/AppDropdown.vue';
import AppButton from '../../ui/AppButton.vue';

const emit = defineEmits(['select']);
defineProps({
    disabled: {
        type: Boolean,
        default: false,
    },
});

const printOptions = [
    {
        id: 'conference',
        title: 'Conferência da mesa',
        hint: 'Uso interno • não fiscal',
    },
    {
        id: 'non_fiscal_receipt',
        title: 'Cupom/Comprovante',
        hint: 'Documento não fiscal',
    },
    {
        id: 'kitchen_order',
        title: 'Pedido da cozinha',
        hint: 'Separação operacional',
    },
    {
        id: 'bar_order',
        title: 'Pedido do bar',
        hint: 'Separação operacional',
    },
];

function handleSelect(optionId) {
    emit('select', optionId);
}
</script>

<template>
    <AppDropdown placement="bottom-end" :disabled="disabled">
        <template #trigger>
            <AppButton variant="secondary" class="print-dropdown-trigger" :disabled="disabled">
                <Printer class="h-4 w-4" aria-hidden="true" />
                <span>Impressões</span>
            </AppButton>
        </template>

        <div class="print-dropdown-root">
            <button
                v-for="option in printOptions"
                :key="option.id"
                type="button"
                class="print-dropdown-item"
                @click="handleSelect(option.id)"
            >
                <strong>{{ option.title }}</strong>
                <small>{{ option.hint }}</small>
            </button>
        </div>
    </AppDropdown>
</template>

<style scoped>
.print-dropdown-trigger {
    display: inline-flex;
    align-items: center;
    gap: 0.38rem;
}

.print-dropdown-root {
    display: grid;
    gap: 0.28rem;
}

.print-dropdown-item {
    border-radius: var(--radius-sm);
    border: 1px solid transparent;
    background: transparent;
    color: var(--color-text);
    text-align: left;
    padding: 0.5rem 0.58rem;
    display: grid;
    gap: 0.08rem;
    transition: all var(--transition-fast);
}

.print-dropdown-item:hover {
    border-color: color-mix(in srgb, var(--color-primary) 40%, transparent);
    background: color-mix(in srgb, var(--color-primary) 11%, var(--color-bg-surface));
}

.print-dropdown-item strong {
    font-size: 0.79rem;
    font-weight: 700;
}

.print-dropdown-item small {
    font-size: 0.68rem;
    color: var(--color-text-muted);
}
</style>
