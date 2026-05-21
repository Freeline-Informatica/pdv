<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Pencil, Plus } from 'lucide-vue-next';
import api from '../../lib/api';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppIconButton from '../../components/ui/AppIconButton.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppModal from '../../components/ui/AppModal.vue';
import AppSearchField from '../../components/ui/AppSearchField.vue';
import AppTextarea from '../../components/ui/AppTextarea.vue';
import AppSwitch from '../../components/ui/AppSwitch.vue';

const loading = ref(false);
const saving = ref(false);
const dialogOpen = ref(false);
const editingId = ref(null);
const items = ref([]);
const search = ref('');
const pageError = ref('');
const dialogError = ref('');

const form = reactive({
    nome: '',
    documento: '',
    telefone: '',
    email: '',
    endereco: '',
    observacoes: '',
    ativo: true,
});

const dialogTitle = computed(() => (editingId.value ? 'Editar Fornecedor' : 'Novo Fornecedor'));
const saveLabel = computed(() => (editingId.value ? 'Salvar' : 'Cadastrar'));

const filteredItems = computed(() => {
    const needle = String(search.value || '').trim().toLowerCase();
    if (!needle) return items.value;

    return items.value.filter((item) => {
        const name = String(item.nome || '').toLowerCase();
        const document = String(item.documento || '').toLowerCase();
        const phone = String(item.telefone || '').toLowerCase();
        const email = String(item.email || '').toLowerCase();

        return name.includes(needle) || document.includes(needle) || phone.includes(needle) || email.includes(needle);
    });
});

function formatDocument(value) {
    const digits = String(value || '').replace(/\D/g, '');
    if (digits.length === 11) {
        return digits
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    }

    if (digits.length === 14) {
        return digits
            .replace(/(\d{2})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1/$2')
            .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
    }

    return String(value || '');
}

function resetForm() {
    form.nome = '';
    form.documento = '';
    form.telefone = '';
    form.email = '';
    form.endereco = '';
    form.observacoes = '';
    form.ativo = true;
}

function openCreate() {
    editingId.value = null;
    dialogError.value = '';
    resetForm();
    dialogOpen.value = true;
}

function openEdit(item) {
    editingId.value = item.id;
    dialogError.value = '';
    form.nome = item.nome || '';
    form.documento = formatDocument(item.documento || '');
    form.telefone = item.telefone || '';
    form.email = item.email || '';
    form.endereco = item.endereco || '';
    form.observacoes = item.observacoes || '';
    form.ativo = !!item.ativo;
    dialogOpen.value = true;
}

async function loadItems() {
    loading.value = true;
    pageError.value = '';

    try {
        const { data } = await api.get('/suppliers');
        items.value = Array.isArray(data) ? data : [];
    } catch (requestError) {
        items.value = [];
        pageError.value = requestError?.response?.data?.message ?? 'Falha ao carregar fornecedores.';
    } finally {
        loading.value = false;
    }
}

async function saveSupplier() {
    if (!form.nome.trim()) {
        dialogError.value = 'Informe o nome do fornecedor.';
        return;
    }

    dialogError.value = '';
    saving.value = true;
    try {
        const payload = {
            nome: form.nome.trim(),
            documento: form.documento || null,
            telefone: form.telefone || null,
            email: form.email || null,
            endereco: form.endereco || null,
            observacoes: form.observacoes || null,
            ativo: !!form.ativo,
        };

        if (editingId.value) {
            await api.put(`/suppliers/${editingId.value}`, payload);
        } else {
            await api.post('/suppliers', payload);
        }

        dialogOpen.value = false;
        await loadItems();
    } catch (requestError) {
        const validationErrors = requestError?.response?.data?.errors || {};
        if (Array.isArray(validationErrors.nome) && validationErrors.nome.length > 0) {
            dialogError.value = validationErrors.nome[0];
        } else if (Array.isArray(validationErrors.documento) && validationErrors.documento.length > 0) {
            dialogError.value = validationErrors.documento[0];
        } else if (Array.isArray(validationErrors.email) && validationErrors.email.length > 0) {
            dialogError.value = validationErrors.email[0];
        } else {
            dialogError.value = requestError?.response?.data?.message ?? 'Não foi possível salvar o fornecedor.';
        }
    } finally {
        saving.value = false;
    }
}

onMounted(loadItems);
</script>

<template>
    <div class="space-y-4">
        <SettingsPageHeader title="Fornecedores" subtitle="Cadastro de fornecedores para compras">
            <template #actions>
                <AppButton :disabled="loading" @click="openCreate">
                    <Plus class="h-4 w-4" aria-hidden="true" />
                    Novo Fornecedor
                </AppButton>
            </template>
        </SettingsPageHeader>

        <p v-if="pageError" class="text-sm text-danger">{{ pageError }}</p>

        <div class="ui-card suppliers-search-shell">
            <AppSearchField
                v-model="search"
                placeholder="Buscar por nome ou CNPJ/CPF..."
                class="suppliers-search-input"
            />
        </div>

        <div class="ui-table-wrap suppliers-table-shell">
            <table class="ui-table suppliers-table">
                <thead>
                    <tr>
                        <th class="suppliers-col-name">Nome</th>
                        <th class="suppliers-col-document">CNPJ/CPF</th>
                        <th class="suppliers-col-phone">Telefone</th>
                        <th class="suppliers-col-email">Email</th>
                        <th class="suppliers-col-status">Status</th>
                        <th class="suppliers-col-actions">Ações</th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-if="loading">
                        <td colspan="6" class="suppliers-empty">Carregando fornecedores...</td>
                    </tr>

                    <tr v-else-if="filteredItems.length === 0">
                        <td colspan="6" class="suppliers-empty">
                            Nenhum fornecedor encontrado.
                        </td>
                    </tr>

                    <tr v-for="item in filteredItems" :key="item.id">
                        <td class="suppliers-name-cell">{{ item.nome }}</td>
                        <td class="suppliers-document-cell">{{ formatDocument(item.documento) || '—' }}</td>
                        <td class="suppliers-phone-cell">{{ item.telefone || '—' }}</td>
                        <td class="suppliers-email-cell">{{ item.email || '—' }}</td>
                        <td>
                            <div class="suppliers-status-cell">
                                <AppBadge :variant="item.ativo ? 'success' : 'default'">
                                    {{ item.ativo ? 'Ativo' : 'Inativo' }}
                                </AppBadge>
                            </div>
                        </td>
                        <td>
                            <div class="suppliers-actions-cell">
                                <AppIconButton title="Editar fornecedor" @click="openEdit(item)">
                                    <Pencil class="h-4 w-4" aria-hidden="true" />
                                </AppIconButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppModal
            :open="dialogOpen"
            :title="dialogTitle"
            width-class="max-w-3xl"
            @close="dialogOpen = false"
        >
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <AppInput v-model="form.nome" label="Nome *" class="md:col-span-2" />
                <AppInput v-model="form.documento" label="CNPJ/CPF" />
                <AppInput v-model="form.telefone" label="Telefone" />
                <AppInput v-model="form.email" label="Email" type="email" class="md:col-span-2" />
                <AppInput v-model="form.endereco" label="Endereço" class="md:col-span-2" />
                <AppTextarea v-model="form.observacoes" label="Observações" rows="3" class="md:col-span-2" />

                <div class="md:col-span-2">
                    <AppSwitch v-model="form.ativo" label="Fornecedor ativo" />
                </div>
            </div>

            <p v-if="dialogError" class="suppliers-dialog-error">{{ dialogError }}</p>

            <div class="mt-5 flex justify-end gap-2">
                <AppButton variant="secondary" @click="dialogOpen = false">Cancelar</AppButton>
                <AppButton :loading="saving" @click="saveSupplier">{{ saveLabel }}</AppButton>
            </div>
        </AppModal>
    </div>
</template>

<style scoped>
.suppliers-search-shell {
    padding: 1rem;
}

.suppliers-search-input {
    max-width: 32rem;
}

.suppliers-table-shell {
    border-radius: var(--radius-xl);
}

.suppliers-table {
    table-layout: fixed;
}

.suppliers-col-name,
.suppliers-col-document,
.suppliers-col-phone,
.suppliers-col-email,
.suppliers-col-status,
.suppliers-col-actions {
    text-transform: none;
    letter-spacing: 0;
    font-size: 0.94rem;
    font-weight: 700;
    text-align: left;
}

.suppliers-col-name {
    width: 18%;
}

.suppliers-col-document {
    width: 20%;
}

.suppliers-col-phone {
    width: 16%;
}

.suppliers-col-email {
    width: 24%;
}

.suppliers-col-status {
    width: 12%;
}

.suppliers-col-actions {
    width: 10%;
}

.suppliers-empty {
    text-align: center;
    color: var(--color-text-muted);
    padding: 1.35rem 0.9rem;
}

.suppliers-name-cell {
    font-weight: 700;
    color: var(--color-text);
}

.suppliers-document-cell,
.suppliers-phone-cell,
.suppliers-email-cell {
    color: var(--color-text-muted);
    font-size: 0.94rem;
}

.suppliers-status-cell {
    display: inline-flex;
    align-items: center;
}

.suppliers-actions-cell {
    display: inline-flex;
    align-items: center;
}

.suppliers-dialog-error {
    margin-top: 0.8rem;
    font-size: 0.9rem;
    color: var(--color-danger);
}

@media (max-width: 1100px) {
    .suppliers-table {
        min-width: 54rem;
    }
}
</style>
