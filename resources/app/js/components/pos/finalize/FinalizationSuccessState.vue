<script setup>
import { computed } from 'vue';
import AppButton from '../../ui/AppButton.vue';

const props = defineProps({
    formatCurrency: {
        type: Function,
        required: true,
    },
    receipt: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['print', 'resend', 'new-sale', 'close']);

const isContingency = computed(() => props.receipt?.fiscal?.status === 'contingency_pending' || props.receipt?.status === 'Em contingência');
const isProcessing = computed(() => props.receipt?.fiscal?.status === 'processing' || props.receipt?.status === 'Processando emissão');
const eyebrow = computed(() => {
    if (isContingency.value) return 'Contingência operacional';
    if (isProcessing.value) return 'Emissão em processamento';
    return 'Documento fiscal';
});
const description = computed(() => {
    if (isContingency.value) return 'Documento não fiscal - emissão fiscal pendente.';
    if (isProcessing.value) return 'A emissão foi enviada e seguirá em processamento.';
    return 'Documento autorizado. Escolha a próxima ação.';
});
</script>

<template>
    <section class="success-card">
        <p class="success-eyebrow">{{ eyebrow }}</p>
        <h3 class="success-title">Venda finalizada com sucesso</h3>
        <p class="success-desc">{{ description }}</p>

        <div v-if="receipt" class="success-grid">
            <div>
                <span>Numero</span>
                <strong>{{ receipt.number }}</strong>
            </div>
            <div>
                <span>Serie</span>
                <strong>{{ receipt.series }}</strong>
            </div>
            <div>
                <span>Total</span>
                <strong>{{ formatCurrency(receipt.total) }}</strong>
            </div>
            <div>
                <span>Status</span>
                <strong>{{ receipt.status }}</strong>
            </div>
        </div>

        <div class="success-actions">
            <AppButton variant="secondary" @click="emit('print')">Imprimir DANFE/NFC-e</AppButton>
            <AppButton variant="secondary" @click="emit('resend')">Reenviar comprovante</AppButton>
            <AppButton @click="emit('new-sale')">Iniciar nova venda</AppButton>
            <AppButton variant="ghost" @click="emit('close')">Voltar ao PDV</AppButton>
        </div>
    </section>
</template>

<style scoped>
.success-card {
    border: 1px solid color-mix(in srgb, var(--color-success) 48%, var(--color-border));
    border-radius: var(--radius-md);
    background: color-mix(in srgb, var(--color-success) 10%, var(--color-bg-surface));
    padding: 1rem;
}

.success-eyebrow {
    margin: 0;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 700;
    color: var(--color-success);
}

.success-title {
    margin: 0.35rem 0 0;
    font-size: 1.22rem;
    line-height: 1.2;
    font-weight: 900;
    color: var(--color-text);
}

.success-desc {
    margin: 0.42rem 0 0;
    font-size: 0.86rem;
    color: var(--color-text-muted);
}

.success-grid {
    margin-top: 0.9rem;
    display: grid;
    gap: 0.6rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.success-grid div {
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-success) 26%, transparent);
    background: color-mix(in srgb, var(--color-bg-elevated) 84%, var(--color-bg-surface));
    padding: 0.58rem 0.65rem;
}

.success-grid span {
    display: block;
    font-size: 0.72rem;
    color: var(--color-text-muted);
}

.success-grid strong {
    display: block;
    margin-top: 0.15rem;
    color: var(--color-text);
}

.success-actions {
    margin-top: 1rem;
    display: grid;
    gap: 0.55rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

@media (max-width: 760px) {
    .success-actions {
        grid-template-columns: 1fr;
    }
}
</style>
    
