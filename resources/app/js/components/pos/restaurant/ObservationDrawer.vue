<script setup>
import { ref, watch } from 'vue';
import AppButton from '../../ui/AppButton.vue';
import AppDrawer from '../../ui/AppDrawer.vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    fichaObservation: {
        type: String,
        default: '',
    },
    orderObservation: {
        type: String,
        default: '',
    },
    saving: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'save']);

const localFichaObservation = ref('');
const localOrderObservation = ref('');

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) return;
        localFichaObservation.value = String(props.fichaObservation || '');
        localOrderObservation.value = String(props.orderObservation || '');
    },
);

function handleSave() {
    emit('save', {
        fichaObservation: localFichaObservation.value,
        orderObservation: localOrderObservation.value,
    });
}
</script>

<template>
    <AppDrawer :open="open" title="Observações" @close="emit('close')">
        <div class="observation-drawer">
            <label class="observation-drawer__field">
                <span>Observação da ficha</span>
                <textarea
                    v-model="localFichaObservation"
                    rows="4"
                    maxlength="2000"
                    placeholder="Observação geral da ficha"
                />
            </label>

            <label class="observation-drawer__field">
                <span>Observação do próximo pedido</span>
                <textarea
                    v-model="localOrderObservation"
                    rows="3"
                    maxlength="2000"
                    placeholder="Observação do pedido atual"
                />
            </label>

            <AppButton :loading="saving" @click="handleSave">Salvar observações</AppButton>
        </div>
    </AppDrawer>
</template>

<style scoped>
.observation-drawer {
    display: grid;
    gap: 0.68rem;
}

.observation-drawer__field {
    display: grid;
    gap: 0.33rem;
}

.observation-drawer__field span {
    font-size: 0.83rem;
    color: var(--color-text-muted);
    font-weight: 700;
}

.observation-drawer__field textarea {
    width: 100%;
    border: 1px solid var(--color-border);
    border-radius: 0.66rem;
    background: var(--color-bg-elevated);
    color: var(--color-text);
    padding: 0.58rem;
    resize: vertical;
    min-height: 5rem;
}
</style>
