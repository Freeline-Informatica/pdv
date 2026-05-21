<script setup>
import AppInput from '../../ui/AppInput.vue';
import AppSelect from '../../ui/AppSelect.vue';

const props = defineProps({
    documentModel: {
        type: String,
        default: 'NFC-e',
    },
    documentSeries: {
        type: String,
        default: '1',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:documentModel', 'update:documentSeries']);
</script>

<template>
    <section class="fiscal-document-selector">
        <header class="fiscal-document-selector__head">
            <p class="fiscal-document-selector__title">Documento Fiscal</p>
            <p class="fiscal-document-selector__subtitle">Defina o modelo e série antes da numeração da nota.</p>
        </header>

        <div class="fiscal-document-selector__fields">
            <AppSelect
                :model-value="documentModel"
                label="Modelo"
                :disabled="disabled"
                @update:model-value="emit('update:documentModel', $event)"
            >
                <option value="NFC-e">NFC-e (consumidor final)</option>
                <option value="NF-e">NF-e (faturamento)</option>
            </AppSelect>

            <AppInput
                :model-value="documentSeries"
                label="Série"
                :disabled="disabled"
                placeholder="Ex.: 1"
                @update:model-value="emit('update:documentSeries', $event)"
            />
        </div>

        <p class="fiscal-document-selector__warning">
            Em impressões operacionais, lembrar: não é documento fiscal.
        </p>
    </section>
</template>

<style scoped>
.fiscal-document-selector {
    border-radius: var(--radius-md);
    border: 1px solid color-mix(in srgb, var(--color-primary) 34%, var(--color-border));
    background: color-mix(in srgb, var(--color-primary) 8%, var(--color-bg-surface));
    padding: 0.65rem;
    display: grid;
    gap: 0.52rem;
}

.fiscal-document-selector__head {
    display: grid;
    gap: 0.1rem;
}

.fiscal-document-selector__title {
    margin: 0;
    font-size: 0.86rem;
    font-weight: 800;
    color: var(--color-text);
}

.fiscal-document-selector__subtitle {
    margin: 0;
    font-size: 0.72rem;
    color: var(--color-text-muted);
}

.fiscal-document-selector__fields {
    display: grid;
    gap: 0.5rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.fiscal-document-selector__warning {
    margin: 0;
    font-size: 0.68rem;
    color: var(--color-warning);
    font-weight: 700;
}

@media (max-width: 720px) {
    .fiscal-document-selector__fields {
        grid-template-columns: 1fr;
    }
}
</style>
