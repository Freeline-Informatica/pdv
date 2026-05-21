<script setup>
import { reactive, watch } from 'vue';
import AppButton from '../../ui/AppButton.vue';
import AppInput from '../../ui/AppInput.vue';
import AppModal from '../../ui/AppModal.vue';
import AppSelect from '../../ui/AppSelect.vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    table: {
        type: Object,
        default: null,
    },
    command: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'confirm']);

const form = reactive({
    transferMode: 'partial',
    destinationType: 'command',
    destinationCode: '',
    quantity: '1',
    reason: '',
});

watch(
    () => props.open,
    (open) => {
        if (!open) return;

        form.transferMode = 'partial';
        form.destinationType = 'command';
        form.destinationCode = '';
        form.quantity = '1';
        form.reason = '';
    },
);

function submitTransfer() {
    emit('confirm', {
        transferMode: form.transferMode,
        destinationType: form.destinationType,
        destinationCode: String(form.destinationCode || '').trim(),
        quantity: Number(form.quantity || 0),
        reason: String(form.reason || '').trim(),
        originTableId: props.table?.id || null,
        originCommandId: props.command?.id || null,
    });
}
</script>

<template>
    <AppModal
        :open="open"
        title="Transferência de Itens"
        width-class="max-w-xl"
        @close="emit('close')"
    >
        <div class="transfer-sheet-root">
            <p class="text-sm text-muted">
                Estrutura pronta para transferência total ou parcial com auditoria da origem e destino.
            </p>

            <div class="transfer-sheet-grid">
                <AppSelect v-model="form.transferMode" label="Modo de transferência">
                    <option value="partial">Parcial</option>
                    <option value="full">Total</option>
                </AppSelect>

                <AppSelect v-model="form.destinationType" label="Destino">
                    <option value="command">Comanda/Ficha</option>
                    <option value="table">Mesa</option>
                </AppSelect>

                <AppInput
                    v-model="form.destinationCode"
                    class="md:col-span-2"
                    label="Código de destino"
                    placeholder="Ex.: 1205 ou mesa 17"
                />

                <AppInput
                    v-if="form.transferMode === 'partial'"
                    v-model="form.quantity"
                    label="Quantidade"
                    type="number"
                    min="1"
                    step="1"
                />

                <AppInput
                    v-model="form.reason"
                    :class="form.transferMode === 'partial' ? '' : 'md:col-span-2'"
                    label="Motivo / observação"
                    placeholder="Ex.: cliente mudou de mesa"
                />
            </div>

            <div class="transfer-sheet-foot">
                <p class="transfer-sheet-warning">Ação operacional. Não gera documento fiscal.</p>
                <div class="flex items-center gap-2">
                    <AppButton variant="secondary" @click="emit('close')">Cancelar</AppButton>
                    <AppButton @click="submitTransfer">Salvar estrutura</AppButton>
                </div>
            </div>
        </div>
    </AppModal>
</template>

<style scoped>
.transfer-sheet-root {
    display: grid;
    gap: 0.75rem;
}

.transfer-sheet-grid {
    display: grid;
    gap: 0.6rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.transfer-sheet-foot {
    border-top: 1px dashed color-mix(in srgb, var(--color-border) 72%, transparent);
    padding-top: 0.65rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.transfer-sheet-warning {
    margin: 0;
    font-size: 0.72rem;
    color: var(--color-warning);
    font-weight: 700;
}

@media (max-width: 760px) {
    .transfer-sheet-grid {
        grid-template-columns: 1fr;
    }

    .transfer-sheet-foot {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>
