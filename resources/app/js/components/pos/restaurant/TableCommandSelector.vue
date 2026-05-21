<script setup>
import { ref, watch } from 'vue';
import AppButton from '../../ui/AppButton.vue';
import AppCheckbox from '../../ui/AppCheckbox.vue';
import AppInput from '../../ui/AppInput.vue';
import AppSelect from '../../ui/AppSelect.vue';

const props = defineProps({
    tables: {
        type: Array,
        default: () => [],
    },
    commands: {
        type: Array,
        default: () => [],
    },
    selectedTableId: {
        type: String,
        default: null,
    },
    selectedCommandId: {
        type: String,
        default: null,
    },
    creatingFicha: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:selectedTableId', 'update:selectedCommandId', 'create-ficha']);

const fichaFormOpen = ref(false);
const randomCustomer = ref(true);
const customerName = ref('');
const fichaCode = ref('');

watch(
    () => props.selectedTableId,
    () => {
        fichaFormOpen.value = false;
        randomCustomer.value = true;
        customerName.value = '';
        fichaCode.value = '';
    },
);

function toggleFichaForm() {
    fichaFormOpen.value = !fichaFormOpen.value;
}

function submitCreateFicha() {
    emit('create-ficha', {
        randomCustomer: randomCustomer.value,
        customerName: customerName.value,
        fichaCode: fichaCode.value,
    });
}
</script>

<template>
    <div class="table-command-selector ui-card">
        <AppSelect
            label="Mesa"
            :model-value="selectedTableId"
            @update:model-value="emit('update:selectedTableId', $event)"
        >
            <option v-for="table in tables" :key="table.id" :value="table.id">
                Mesa {{ table.code }}
            </option>
        </AppSelect>

        <AppSelect
            label="Ficha"
            :model-value="selectedCommandId"
            @update:model-value="emit('update:selectedCommandId', $event)"
        >
            <option v-for="command in commands" :key="command.id" :value="command.id">
                Ficha {{ command.code }}
            </option>
        </AppSelect>

        <div class="table-command-selector__actions">
            <AppButton variant="secondary" @click="toggleFichaForm">
                Adicionar nova ficha a esta mesa
            </AppButton>
        </div>

        <div v-if="fichaFormOpen" class="table-command-selector__new-ficha">
            <AppCheckbox
                :model-value="randomCustomer"
                label="Cliente aleatório"
                @update:model-value="randomCustomer = $event"
            />

            <AppInput
                v-if="!randomCustomer"
                :model-value="customerName"
                label="Nome do cliente"
                placeholder="Ex: Joao"
                @update:model-value="customerName = $event"
            />

            <AppInput
                :model-value="fichaCode"
                label="Codigo da ficha (opcional)"
                placeholder="Ex: F01-010"
                @update:model-value="fichaCode = $event"
            />

            <AppButton :loading="creatingFicha" @click="submitCreateFicha">Criar ficha</AppButton>
        </div>
    </div>
</template>

<style scoped>
.table-command-selector {
    padding: 0.72rem;
    display: grid;
    gap: 0.6rem;
}

.table-command-selector__actions {
    display: flex;
}

.table-command-selector__new-ficha {
    border: 1px solid var(--color-border);
    border-radius: 0.7rem;
    padding: 0.65rem;
    display: grid;
    gap: 0.55rem;
}
</style>
