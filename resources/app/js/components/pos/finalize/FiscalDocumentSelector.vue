<script setup>
import AppInput from '../../ui/AppInput.vue';

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
    nfceEnabled: {
        type: Boolean,
        default: true,
    },
    nfeEnabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:documentModel', 'update:documentSeries']);

function selectDocumentModel(model) {
    if (props.disabled) return;

    emit('update:documentModel', model);
}
</script>

<template>
    <section class="fiscal-document-selector">
        <header class="fiscal-document-selector__head">
            <p class="fiscal-document-selector__title">Documento Fiscal</p>
            <p class="fiscal-document-selector__subtitle">Defina o modelo e série antes da numeração da nota.</p>
        </header>

        <div class="fiscal-document-selector__models" role="radiogroup" aria-label="Modelo fiscal">
            <button
                type="button"
                class="fiscal-document-selector__model"
                :class="{ 'is-active': documentModel !== 'NF-e' }"
                :disabled="disabled"
                role="radio"
                :aria-checked="documentModel !== 'NF-e'"
                @click="selectDocumentModel('NFC-e')"
            >
                <span>NFC-e</span>
                <small>Venda de balcão</small>
            </button>
            <button
                type="button"
                class="fiscal-document-selector__model"
                :class="{ 'is-active': documentModel === 'NF-e' }"
                :disabled="disabled"
                role="radio"
                :aria-checked="documentModel === 'NF-e'"
                @click="selectDocumentModel('NF-e')"
            >
                <span>NF-e</span>
                <small>Faturamento</small>
            </button>
        </div>

        <div class="fiscal-document-selector__fields">
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
    grid-template-columns: minmax(0, 1fr);
}

.fiscal-document-selector__models {
    display: grid;
    gap: 0.5rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.fiscal-document-selector__model {
    border-radius: var(--radius-md);
    border: 1px solid var(--color-border);
    background: var(--color-bg-surface);
    color: var(--color-text);
    display: grid;
    gap: 0.16rem;
    min-height: 4.2rem;
    padding: 0.72rem;
    text-align: left;
    transition: border-color var(--transition-fast), background var(--transition-fast), transform var(--transition-fast);
}

.fiscal-document-selector__model:hover:not(:disabled),
.fiscal-document-selector__model:focus-visible:not(:disabled) {
    border-color: color-mix(in srgb, var(--color-primary) 56%, var(--color-border));
    transform: translateY(-1px);
}

.fiscal-document-selector__model.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 66%, var(--color-border));
    background: color-mix(in srgb, var(--color-primary) 14%, var(--color-bg-surface));
}

.fiscal-document-selector__model:disabled {
    cursor: not-allowed;
    opacity: 0.52;
}

.fiscal-document-selector__model span {
    font-size: 1rem;
    font-weight: 900;
}

.fiscal-document-selector__model small {
    color: var(--color-text-muted);
    font-size: 0.74rem;
}

.fiscal-document-selector__warning {
    margin: 0;
    font-size: 0.68rem;
    color: var(--color-warning);
    font-weight: 700;
}

@media (max-width: 720px) {
    .fiscal-document-selector__models,
    .fiscal-document-selector__fields {
        grid-template-columns: 1fr;
    }
}
</style>
