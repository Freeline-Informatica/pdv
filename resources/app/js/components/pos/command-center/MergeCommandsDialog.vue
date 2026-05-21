<script setup>
import { computed, reactive, watch } from 'vue';
import AppButton from '../../ui/AppButton.vue';
import AppModal from '../../ui/AppModal.vue';
import AppSelect from '../../ui/AppSelect.vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    tables: {
        type: Array,
        default: () => [],
    },
    selectedTableId: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'confirm']);

const form = reactive({
    sourceCommandId: '',
    destinationCommandId: '',
    keepOriginalOpenDate: true,
});

const commandOptions = computed(() => (
    props.tables.flatMap((table) =>
        (Array.isArray(table.commands) ? table.commands : []).map((command) => ({
            id: command.id,
            label: `Mesa ${table.code} • Ficha ${command.code}`,
            tableId: table.id,
        })),
    )
));

watch(
    () => props.open,
    (open) => {
        if (!open) return;

        form.sourceCommandId = '';
        form.destinationCommandId = '';
        form.keepOriginalOpenDate = true;
    },
);

function submitMerge() {
    emit('confirm', {
        sourceCommandId: form.sourceCommandId,
        destinationCommandId: form.destinationCommandId,
        keepOriginalOpenDate: form.keepOriginalOpenDate,
    });
}
</script>

<template>
    <AppModal
        :open="open"
        title="Juntar Fichas / Comandas"
        width-class="max-w-xl"
        @close="emit('close')"
    >
        <div class="merge-dialog-root">
            <p class="text-sm text-muted">
                Estrutura preparada para junção de comandas mantendo rastreabilidade e data de abertura coerente.
            </p>

            <div class="merge-dialog-grid">
                <AppSelect v-model="form.sourceCommandId" label="Origem">
                    <option value="">Selecione a ficha de origem</option>
                    <option v-for="option in commandOptions" :key="`source-${option.id}`" :value="option.id">
                        {{ option.label }}
                    </option>
                </AppSelect>

                <AppSelect v-model="form.destinationCommandId" label="Destino">
                    <option value="">Selecione a ficha de destino</option>
                    <option v-for="option in commandOptions" :key="`dest-${option.id}`" :value="option.id">
                        {{ option.label }}
                    </option>
                </AppSelect>
            </div>

            <label class="ui-checkbox-wrap">
                <input
                    type="checkbox"
                    class="ui-checkbox"
                    :checked="form.keepOriginalOpenDate"
                    @change="form.keepOriginalOpenDate = $event.target.checked"
                >
                <span>Manter data/hora de abertura da ficha original</span>
            </label>

            <div class="merge-dialog-foot">
                <p class="text-xs text-muted">
                    A união final será implementada com histórico de auditoria item a item.
                </p>
                <div class="flex items-center gap-2">
                    <AppButton variant="secondary" @click="emit('close')">Cancelar</AppButton>
                    <AppButton
                        :disabled="!form.sourceCommandId || !form.destinationCommandId || form.sourceCommandId === form.destinationCommandId"
                        @click="submitMerge"
                    >
                        Salvar estrutura
                    </AppButton>
                </div>
            </div>
        </div>
    </AppModal>
</template>

<style scoped>
.merge-dialog-root {
    display: grid;
    gap: 0.75rem;
}

.merge-dialog-grid {
    display: grid;
    gap: 0.6rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.merge-dialog-foot {
    border-top: 1px dashed color-mix(in srgb, var(--color-border) 72%, transparent);
    padding-top: 0.6rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

@media (max-width: 760px) {
    .merge-dialog-grid {
        grid-template-columns: 1fr;
    }

    .merge-dialog-foot {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>
