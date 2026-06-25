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
import AppSelect from '../../components/ui/AppSelect.vue';
import AppSwitch from '../../components/ui/AppSwitch.vue';
import AppTextarea from '../../components/ui/AppTextarea.vue';

const loading = ref(false);
const saving = ref(false);
const dialogOpen = ref(false);
const editingId = ref(null);
const items = ref([]);
const search = ref('');
const pageError = ref('');
const dialogError = ref('');

const form = reactive({
    tipo_pessoa: 'fisica',
    cpf_cnpj: '',
    nome: '',
    nome_fantasia: '',
    telefone: '',
    email: '',
    cep: '',
    logradouro: '',
    numero: '',
    bairro: '',
    complemento: '',
    cidade: '',
    uf: '',
    pais: 'Brasil',
    inscricao_estadual: '',
    observacoes: '',
    ativo: true,
});

const dialogTitle = computed(() => (editingId.value ? 'Editar Cliente' : 'Novo Cliente'));
const saveLabel = computed(() => (editingId.value ? 'Salvar' : 'Cadastrar'));

const filteredItems = computed(() => {
    const needle = String(search.value || '').trim().toLowerCase();
    const digits = onlyDigits(needle);
    if (!needle) return items.value;

    return items.value.filter((item) => {
        return (
            String(item.nome || '').toLowerCase().includes(needle) ||
            String(item.nome_fantasia || '').toLowerCase().includes(needle) ||
            String(item.email || '').toLowerCase().includes(needle) ||
            (digits && onlyDigits(item.cpf_cnpj).includes(digits)) ||
            (digits && onlyDigits(item.telefone).includes(digits))
        );
    });
});

function onlyDigits(value) {
    return String(value || '').replace(/\D/g, '');
}

function formatDocument(value) {
    const digits = onlyDigits(value);
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
    form.tipo_pessoa = 'fisica';
    form.cpf_cnpj = '';
    form.nome = '';
    form.nome_fantasia = '';
    form.telefone = '';
    form.email = '';
    form.cep = '';
    form.logradouro = '';
    form.numero = '';
    form.bairro = '';
    form.complemento = '';
    form.cidade = '';
    form.uf = '';
    form.pais = 'Brasil';
    form.inscricao_estadual = '';
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
    form.tipo_pessoa = item.tipo_pessoa || 'fisica';
    form.cpf_cnpj = formatDocument(item.cpf_cnpj || '');
    form.nome = item.nome || '';
    form.nome_fantasia = item.nome_fantasia || '';
    form.telefone = item.telefone || '';
    form.email = item.email || '';
    form.cep = item.cep || '';
    form.logradouro = item.logradouro || '';
    form.numero = item.numero || '';
    form.bairro = item.bairro || '';
    form.complemento = item.complemento || '';
    form.cidade = item.cidade || '';
    form.uf = item.uf || '';
    form.pais = item.pais || 'Brasil';
    form.inscricao_estadual = item.inscricao_estadual || '';
    form.observacoes = item.observacoes || '';
    form.ativo = !!item.ativo;
    dialogOpen.value = true;
}

async function loadItems() {
    loading.value = true;
    pageError.value = '';

    try {
        const { data } = await api.get('/customers');
        items.value = Array.isArray(data) ? data : [];
    } catch (requestError) {
        items.value = [];
        pageError.value = requestError?.response?.data?.message ?? 'Falha ao carregar clientes.';
    } finally {
        loading.value = false;
    }
}

async function saveCustomer() {
    if (!form.nome.trim()) {
        dialogError.value = 'Informe o nome do cliente.';
        return;
    }

    dialogError.value = '';
    saving.value = true;
    try {
        const payload = {
            tipo_pessoa: form.tipo_pessoa,
            cpf_cnpj: form.cpf_cnpj || null,
            nome: form.nome.trim(),
            nome_fantasia: form.nome_fantasia || null,
            telefone: form.telefone || null,
            email: form.email || null,
            cep: form.cep || null,
            logradouro: form.logradouro || null,
            numero: form.numero || null,
            bairro: form.bairro || null,
            complemento: form.complemento || null,
            cidade: form.cidade || null,
            uf: form.uf || null,
            pais: form.pais || 'Brasil',
            inscricao_estadual: form.inscricao_estadual || null,
            observacoes: form.observacoes || null,
            ativo: !!form.ativo,
        };

        if (editingId.value) {
            await api.put(`/customers/${editingId.value}`, payload);
        } else {
            await api.post('/customers', payload);
        }

        dialogOpen.value = false;
        await loadItems();
    } catch (requestError) {
        const validationErrors = requestError?.response?.data?.errors || {};
        dialogError.value = Object.values(validationErrors).flat()[0]
            || requestError?.response?.data?.message
            || 'Não foi possível salvar o cliente.';
    } finally {
        saving.value = false;
    }
}

onMounted(loadItems);
</script>

<template>
    <div class="space-y-4">
        <SettingsPageHeader title="Clientes" subtitle="Cadastro usado no checkout e na emissao fiscal">
            <template #actions>
                <AppButton :disabled="loading" @click="openCreate">
                    <Plus class="h-4 w-4" aria-hidden="true" />
                    Novo Cliente
                </AppButton>
            </template>
        </SettingsPageHeader>

        <p v-if="pageError" class="text-sm text-danger">{{ pageError }}</p>

        <div class="ui-card customers-search-shell">
            <AppSearchField v-model="search" placeholder="Buscar por nome, CPF/CNPJ, telefone ou email..." />
        </div>

        <div class="ui-table-wrap customers-table-shell">
            <table class="ui-table customers-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>CPF/CNPJ</th>
                        <th>Telefone</th>
                        <th>Cidade/UF</th>
                        <th>Status</th>
                        <th class="customers-col-actions">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="6" class="customers-empty">Carregando clientes...</td>
                    </tr>
                    <tr v-else-if="filteredItems.length === 0">
                        <td colspan="6" class="customers-empty">Nenhum cliente encontrado.</td>
                    </tr>
                    <tr v-for="item in filteredItems" :key="item.id">
                        <td>
                            <strong>{{ item.nome }}</strong>
                            <span v-if="item.nome_fantasia" class="customers-muted">{{ item.nome_fantasia }}</span>
                        </td>
                        <td>{{ formatDocument(item.cpf_cnpj) || '-' }}</td>
                        <td>{{ item.telefone || '-' }}</td>
                        <td>{{ item.cidade || '-' }}<span v-if="item.uf">/{{ item.uf }}</span></td>
                        <td>
                            <AppBadge :variant="item.ativo ? 'success' : 'default'">
                                {{ item.ativo ? 'Ativo' : 'Inativo' }}
                            </AppBadge>
                        </td>
                        <td>
                            <AppIconButton title="Editar cliente" @click="openEdit(item)">
                                <Pencil class="h-4 w-4" aria-hidden="true" />
                            </AppIconButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppModal :open="dialogOpen" :title="dialogTitle" width-class="max-w-4xl" @close="dialogOpen = false">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <AppSelect v-model="form.tipo_pessoa" label="Tipo de pessoa">
                    <option value="fisica">Pessoa física</option>
                    <option value="juridica">Pessoa juridica</option>
                </AppSelect>
                <AppInput v-model="form.cpf_cnpj" label="CPF/CNPJ" />
                <AppInput v-model="form.nome" label="Nome / Razao social *" class="md:col-span-2" />
                <AppInput v-model="form.nome_fantasia" label="Nome fantasia" />
                <AppInput v-model="form.inscricao_estadual" label="Inscricao estadual" />
                <AppInput v-model="form.telefone" label="Telefone" />
                <AppInput v-model="form.email" label="Email" type="email" />
                <AppInput v-model="form.cep" label="CEP" />
                <AppInput v-model="form.numero" label="Número" />
                <AppInput v-model="form.logradouro" label="Logradouro" />
                <AppInput v-model="form.bairro" label="Bairro" />
                <AppInput v-model="form.complemento" label="Complemento" />
                <AppInput v-model="form.cidade" label="Cidade" />
                <AppInput v-model="form.uf" label="UF" />
                <AppInput v-model="form.pais" label="Pais" />
                <AppTextarea v-model="form.observacoes" label="Observacoes" rows="3" class="md:col-span-2" />
                <div class="md:col-span-2">
                    <AppSwitch v-model="form.ativo" label="Cliente ativo" />
                </div>
            </div>

            <p v-if="dialogError" class="customers-dialog-error">{{ dialogError }}</p>

            <div class="mt-5 flex justify-end gap-2">
                <AppButton variant="secondary" @click="dialogOpen = false">Cancelar</AppButton>
                <AppButton :loading="saving" @click="saveCustomer">{{ saveLabel }}</AppButton>
            </div>
        </AppModal>
    </div>
</template>

<style scoped>
.customers-search-shell {
    padding: 0.75rem;
}

.customers-table-shell {
    overflow: auto;
}

.customers-table th,
.customers-table td {
    vertical-align: middle;
}

.customers-table td:first-child {
    min-width: 220px;
}

.customers-muted {
    display: block;
    color: var(--color-text-muted);
    font-size: 0.76rem;
    margin-top: 0.12rem;
}

.customers-col-actions {
    width: 88px;
}

.customers-empty {
    padding: 2rem 1rem;
    text-align: center;
    color: var(--color-text-muted);
}

.customers-dialog-error {
    margin: 1rem 0 0;
    color: var(--color-danger);
    font-size: 0.86rem;
    font-weight: 700;
}
</style>
