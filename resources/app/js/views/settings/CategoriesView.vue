<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../../lib/api';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import SettingsTableCard from '../../components/settings/SettingsTableCard.vue';
import SettingsEmptyState from '../../components/settings/SettingsEmptyState.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppTable from '../../components/ui/AppTable.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppModal from '../../components/ui/AppModal.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppTextarea from '../../components/ui/AppTextarea.vue';
import AppCheckbox from '../../components/ui/AppCheckbox.vue';

const loading = ref(false);
const saving = ref(false);
const items = ref([]);
const dialogOpen = ref(false);
const editingId = ref(null);

const form = reactive({
    nome: '',
    descricao: '',
    ativo: true,
});

function resetForm() {
    form.nome = '';
    form.descricao = '';
    form.ativo = true;
}

function openNew() {
    editingId.value = null;
    resetForm();
    dialogOpen.value = true;
}

function openEdit(item) {
    editingId.value = item.id;
    form.nome = item.nome;
    form.descricao = item.descricao || '';
    form.ativo = !!item.ativo;
    dialogOpen.value = true;
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/categories');
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
            descricao: form.descricao || null,
            ativo: form.ativo,
        };

        if (editingId.value) {
            await api.put(`/categories/${editingId.value}`, payload);
        } else {
            await api.post('/categories', payload);
        }

        dialogOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(item) {
    if (!window.confirm(`Excluir categoria \"${item.nome}\"?`)) return;
    await api.delete(`/categories/${item.id}`);
    await load();
}

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <SettingsPageHeader title="Categorias" subtitle="Organize e classifique produtos por grupos de venda.">
            <template #actions>
                <AppButton @click="openNew">Nova categoria</AppButton>
            </template>
        </SettingsPageHeader>

        <SettingsTableCard>
            <AppTable>
                <thead>
                    <tr>
                        <th class="text-left">Nome</th>
                        <th class="text-left">Descrição</th>
                        <th class="text-center">Status</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="4" class="text-center text-muted">Carregando...</td>
                    </tr>
                    <tr v-else-if="items.length === 0">
                        <td colspan="4" class="p-0">
                            <SettingsEmptyState
                                title="Nenhuma categoria cadastrada"
                                description="Crie categorias para facilitar a organização e pesquisa no PDV."
                            >
                                <template #actions>
                                    <AppButton @click="openNew">Nova categoria</AppButton>
                                </template>
                            </SettingsEmptyState>
                        </td>
                    </tr>
                    <tr v-for="item in items" :key="item.id">
                        <td class="font-semibold text-main">{{ item.nome }}</td>
                        <td class="text-muted">{{ item.descricao || '—' }}</td>
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

        <AppModal :open="dialogOpen" :title="editingId ? 'Editar Categoria' : 'Nova Categoria'" @close="dialogOpen = false">
            <div class="space-y-4">
                <AppInput v-model="form.nome" label="Nome" />
                <AppTextarea v-model="form.descricao" label="Descrição" rows="3" />
                <AppCheckbox v-model="form.ativo" label="Categoria ativa" />
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <AppButton variant="secondary" @click="dialogOpen = false">Cancelar</AppButton>
                <AppButton :loading="saving" @click="save">Salvar</AppButton>
            </div>
        </AppModal>
    </div>
</template>
