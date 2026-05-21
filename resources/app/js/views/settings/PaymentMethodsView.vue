<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../../lib/api';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import SettingsEmptyState from '../../components/settings/SettingsEmptyState.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppCard from '../../components/ui/AppCard.vue';
import AppModal from '../../components/ui/AppModal.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppTextarea from '../../components/ui/AppTextarea.vue';
import AppCheckbox from '../../components/ui/AppCheckbox.vue';

const loading = ref(false);
const saving = ref(false);
const items = ref([]);
const dialogOpen = ref(false);
const editingId = ref(null);

const types = [
    { value: 'dinheiro', label: 'Dinheiro' },
    { value: 'credito', label: 'Cartão de Crédito' },
    { value: 'debito', label: 'Cartão de Débito' },
    { value: 'pix', label: 'PIX' },
    { value: 'boleto', label: 'Boleto' },
    { value: 'convenio', label: 'Convênio' },
    { value: 'cheque', label: 'Cheque' },
    { value: 'voucher', label: 'Voucher' },
    { value: 'crediario', label: 'Crediário' },
];

const form = reactive({
    nome: '',
    tipo: 'dinheiro',
    ativo: true,
    ordem_pdv: '0',
    observacoes: '',
    permite_troco: false,
    permite_parcelamento: false,
    permite_multiplos_pagamentos: true,
    tef_habilitado: false,
});

function resetForm() {
    form.nome = '';
    form.tipo = 'dinheiro';
    form.ativo = true;
    form.ordem_pdv = '0';
    form.observacoes = '';
    form.permite_troco = false;
    form.permite_parcelamento = false;
    form.permite_multiplos_pagamentos = true;
    form.tef_habilitado = false;
}

function openNew() {
    editingId.value = null;
    resetForm();
    dialogOpen.value = true;
}

function openEdit(item) {
    editingId.value = item.id;
    form.nome = item.nome;
    form.tipo = item.tipo;
    form.ativo = !!item.ativo;
    form.ordem_pdv = String(item.ordem_pdv || 0);
    form.observacoes = item.observacoes || '';
    form.permite_troco = !!item.permite_troco;
    form.permite_parcelamento = !!item.permite_parcelamento;
    form.permite_multiplos_pagamentos = !!item.permite_multiplos_pagamentos;
    form.tef_habilitado = !!item.tef_habilitado;
    dialogOpen.value = true;
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/payment-methods');
        items.value = data;
    } finally {
        loading.value = false;
    }
}

async function save() {
    if (!form.nome.trim()) return;

    saving.value = true;
    try {
        const payload = {
            nome: form.nome,
            tipo: form.tipo,
            ativo: form.ativo,
            ordem_pdv: Number(form.ordem_pdv || 0),
            observacoes: form.observacoes || null,
            permite_troco: form.tipo === 'dinheiro' ? form.permite_troco : false,
            permite_parcelamento: form.permite_parcelamento,
            permite_multiplos_pagamentos: form.permite_multiplos_pagamentos,
            tef_habilitado: form.tef_habilitado,
            tef_provedor: null,
            tef_adquirente: null,
            parcelas_max: 1,
            parcela_minima: 0,
            taxa_debito: 0,
            taxa_credito_vista: 0,
            taxa_credito_parcelado: 0,
            dias_recebimento: 1,
            parcelas_min: 1,
            sem_juros_ate: 0,
        };

        if (editingId.value) {
            await api.put(`/payment-methods/${editingId.value}`, payload);
        } else {
            await api.post('/payment-methods', payload);
        }

        dialogOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(item) {
    if (!window.confirm(`Excluir meio de pagamento \"${item.nome}\"?`)) return;
    await api.delete(`/payment-methods/${item.id}`);
    await load();
}

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <SettingsPageHeader title="Meios de Pagamento" subtitle="Cadastro e regras de uso no PDV com clareza operacional.">
            <template #actions>
                <AppButton @click="openNew">Novo meio</AppButton>
            </template>
        </SettingsPageHeader>

        <div class="space-y-3">
            <AppCard v-if="loading" class="p-8 text-center text-muted">Carregando meios de pagamento...</AppCard>

            <SettingsEmptyState
                v-else-if="items.length === 0"
                title="Nenhum meio cadastrado"
                description="Cadastre o primeiro meio para habilitar o recebimento no PDV."
            >
                <template #actions>
                    <AppButton @click="openNew">Novo meio</AppButton>
                </template>
            </SettingsEmptyState>

            <AppCard v-for="item in items" :key="item.id" class="p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-main">{{ item.nome }}</h3>
                            <AppBadge :variant="item.ativo ? 'success' : 'default'">
                                {{ item.ativo ? 'Ativo' : 'Inativo' }}
                            </AppBadge>
                        </div>
                        <p class="text-sm text-muted mt-1">
                            Tipo: {{ item.tipo }}
                            <span v-if="item.permite_parcelamento"> • Parcelável</span>
                            <span v-if="item.ordem_pdv"> • Ordem {{ item.ordem_pdv }}</span>
                        </p>
                    </div>

                    <div class="inline-flex items-center gap-2">
                        <AppButton variant="ghost" @click="openEdit(item)">Editar</AppButton>
                        <AppButton variant="danger" @click="remove(item)">Excluir</AppButton>
                    </div>
                </div>
            </AppCard>
        </div>

        <AppModal
            :open="dialogOpen"
            :title="editingId ? 'Editar Meio de Pagamento' : 'Novo Meio de Pagamento'"
            width-class="max-w-3xl"
            @close="dialogOpen = false"
        >
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <AppInput v-model="form.nome" label="Nome" />

                <AppSelect v-model="form.tipo" label="Tipo">
                    <option v-for="item in types" :key="item.value" :value="item.value">{{ item.label }}</option>
                </AppSelect>

                <AppInput v-model="form.ordem_pdv" type="number" min="0" label="Ordem no PDV" />
                <AppTextarea v-model="form.observacoes" label="Observações" rows="2" class="md:col-span-2" />

                <AppCheckbox v-model="form.ativo" label="Ativo" />
                <AppCheckbox v-if="form.tipo === 'dinheiro'" v-model="form.permite_troco" label="Permite troco" />
                <AppCheckbox v-model="form.permite_parcelamento" label="Permite parcelamento" />
                <AppCheckbox v-model="form.permite_multiplos_pagamentos" label="Múltiplos pagamentos" />
                <AppCheckbox v-model="form.tef_habilitado" label="Exige TEF" />
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <AppButton variant="secondary" @click="dialogOpen = false">Cancelar</AppButton>
                <AppButton :loading="saving" @click="save">Salvar</AppButton>
            </div>
        </AppModal>
    </div>
</template>
