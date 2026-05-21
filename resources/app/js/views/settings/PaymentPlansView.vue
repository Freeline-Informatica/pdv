<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import api from '../../lib/api';
import { formatCurrency, formatPercent } from '../../lib/format';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import SettingsFilterBar from '../../components/settings/SettingsFilterBar.vue';
import SettingsTableCard from '../../components/settings/SettingsTableCard.vue';
import SettingsEmptyState from '../../components/settings/SettingsEmptyState.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppTable from '../../components/ui/AppTable.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppModal from '../../components/ui/AppModal.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppCheckbox from '../../components/ui/AppCheckbox.vue';

const loading = ref(false);
const saving = ref(false);
const methods = ref([]);
const plans = ref([]);
const selectedMethodId = ref('');
const dialogOpen = ref(false);
const editingId = ref(null);

const form = reactive({
    nome: '',
    payment_method_id: '',
    ativo: true,
    ordem_pdv: '0',
    quantidade_parcelas: '1',
    intervalo_parcelas: '30',
    valor_minimo_parcela: '0',
    possui_juros: false,
    percentual_juros: '0',
    exibir_pdv: true,
});

const currentMethod = computed(() => methods.value.find((item) => item.id === selectedMethodId.value));

function resetForm() {
    form.nome = '';
    form.payment_method_id = selectedMethodId.value;
    form.ativo = true;
    form.ordem_pdv = '0';
    form.quantidade_parcelas = '1';
    form.intervalo_parcelas = '30';
    form.valor_minimo_parcela = '0';
    form.possui_juros = false;
    form.percentual_juros = '0';
    form.exibir_pdv = true;
}

function openNew() {
    editingId.value = null;
    resetForm();
    dialogOpen.value = true;
}

function openEdit(item) {
    editingId.value = item.id;
    form.nome = item.nome;
    form.payment_method_id = item.payment_method_id;
    form.ativo = !!item.ativo;
    form.ordem_pdv = String(item.ordem_pdv || 0);
    form.quantidade_parcelas = String(item.quantidade_parcelas || 1);
    form.intervalo_parcelas = String(item.intervalo_parcelas || 30);
    form.valor_minimo_parcela = String(item.valor_minimo_parcela || 0);
    form.possui_juros = !!item.possui_juros;
    form.percentual_juros = String(item.percentual_juros || 0);
    form.exibir_pdv = !!item.exibir_pdv;
    dialogOpen.value = true;
}

async function loadMethods() {
    const { data } = await api.get('/payment-methods?active_only=1&installments_only=1');
    methods.value = data;

    if (!selectedMethodId.value && methods.value.length > 0) {
        selectedMethodId.value = methods.value[0].id;
    }
}

async function loadPlans() {
    if (!selectedMethodId.value) {
        plans.value = [];
        return;
    }

    loading.value = true;
    try {
        const { data } = await api.get(`/payment-plans?payment_method_id=${selectedMethodId.value}`);
        plans.value = data;
    } finally {
        loading.value = false;
    }
}

async function save() {
    if (!form.nome.trim() || !form.payment_method_id) return;

    saving.value = true;
    try {
        const payload = {
            nome: form.nome,
            payment_method_id: form.payment_method_id,
            ativo: form.ativo,
            ordem_pdv: Number(form.ordem_pdv || 0),
            quantidade_parcelas: Number(form.quantidade_parcelas || 1),
            intervalo_parcelas: Number(form.intervalo_parcelas || 30),
            valor_minimo_parcela: Number(form.valor_minimo_parcela || 0),
            possui_juros: form.possui_juros,
            percentual_juros: form.possui_juros ? Number(form.percentual_juros || 0) : 0,
            exibir_pdv: form.exibir_pdv,
        };

        if (editingId.value) {
            await api.put(`/payment-plans/${editingId.value}`, payload);
        } else {
            await api.post('/payment-plans', payload);
        }

        dialogOpen.value = false;
        await loadPlans();
    } finally {
        saving.value = false;
    }
}

async function remove(item) {
    if (!window.confirm(`Excluir plano \"${item.nome}\"?`)) return;
    await api.delete(`/payment-plans/${item.id}`);
    await loadPlans();
}

watch(selectedMethodId, () => {
    loadPlans();
});

onMounted(async () => {
    await loadMethods();
    await loadPlans();
});
</script>

<template>
    <div class="space-y-4">
        <SettingsPageHeader title="Planos de Pagamento" subtitle="Parcelas, juros e exibição no PDV por meio de pagamento.">
            <template #actions>
                <AppButton :disabled="!selectedMethodId" @click="openNew">Novo plano</AppButton>
            </template>
        </SettingsPageHeader>

        <SettingsFilterBar>
            <div class="w-full max-w-sm">
                <AppSelect v-model="selectedMethodId" label="Meio de pagamento">
                    <option value="" disabled>Selecione...</option>
                    <option v-for="item in methods" :key="item.id" :value="item.id">{{ item.nome }}</option>
                </AppSelect>
            </div>
        </SettingsFilterBar>

        <SettingsEmptyState
            v-if="!currentMethod"
            title="Nenhum meio com parcelamento habilitado"
            description="Cadastre um meio de pagamento parcelável para começar a criar planos."
        />

        <SettingsTableCard v-else>
            <AppTable>
                <thead>
                    <tr>
                        <th class="text-left">Plano</th>
                        <th class="text-center">Parcelas</th>
                        <th class="text-center">Juros</th>
                        <th class="text-center">Valor mín.</th>
                        <th class="text-center">Status</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="6" class="text-center text-muted">Carregando...</td>
                    </tr>
                    <tr v-else-if="plans.length === 0">
                        <td colspan="6" class="p-0">
                            <SettingsEmptyState
                                title="Nenhum plano cadastrado"
                                description="Cadastre planos para oferecer parcelamentos ao cliente."
                            >
                                <template #actions>
                                    <AppButton @click="openNew">Novo plano</AppButton>
                                </template>
                            </SettingsEmptyState>
                        </td>
                    </tr>
                    <tr v-for="item in plans" :key="item.id">
                        <td class="font-semibold text-main">{{ item.nome }}</td>
                        <td class="text-center">{{ item.quantidade_parcelas }}</td>
                        <td class="text-center">{{ item.possui_juros ? formatPercent(item.percentual_juros) : 'Sem juros' }}</td>
                        <td class="text-center">{{ formatCurrency(item.valor_minimo_parcela) }}</td>
                        <td class="text-center">
                            <AppBadge :variant="item.ativo ? 'success' : 'default'">
                                {{ item.ativo ? 'Ativo' : 'Inativo' }}
                            </AppBadge>
                        </td>
                        <td class="text-right">
                            <div class="inline-flex items-center gap-2">
                                <AppButton variant="ghost" @click="openEdit(item)">Editar</AppButton>
                                <AppButton variant="danger" @click="remove(item)">Excluir</AppButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </AppTable>
        </SettingsTableCard>

        <AppModal :open="dialogOpen" :title="editingId ? 'Editar Plano' : 'Novo Plano'" width-class="max-w-3xl" @close="dialogOpen = false">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <AppInput v-model="form.nome" label="Nome" />

                <AppSelect v-model="form.payment_method_id" label="Meio de pagamento">
                    <option v-for="item in methods" :key="item.id" :value="item.id">{{ item.nome }}</option>
                </AppSelect>

                <AppInput v-model="form.ordem_pdv" type="number" min="0" label="Ordem no PDV" />
                <AppInput v-model="form.quantidade_parcelas" type="number" min="1" label="Parcelas" />
                <AppInput v-model="form.intervalo_parcelas" type="number" min="1" label="Intervalo (dias)" />
                <AppInput v-model="form.valor_minimo_parcela" type="number" min="0" step="0.01" label="Valor mínimo por parcela" />

                <div class="md:col-span-2 flex flex-wrap gap-4">
                    <AppCheckbox v-model="form.possui_juros" label="Possui juros" />
                    <AppCheckbox v-model="form.exibir_pdv" label="Exibir no PDV" />
                    <AppCheckbox v-model="form.ativo" label="Ativo" />
                </div>

                <AppInput
                    v-if="form.possui_juros"
                    v-model="form.percentual_juros"
                    type="number"
                    min="0"
                    step="0.01"
                    label="Percentual de juros"
                />
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <AppButton variant="secondary" @click="dialogOpen = false">Cancelar</AppButton>
                <AppButton :loading="saving" @click="save">Salvar</AppButton>
            </div>
        </AppModal>
    </div>
</template>
