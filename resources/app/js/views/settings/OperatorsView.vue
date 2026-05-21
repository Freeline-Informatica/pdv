<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Pencil, Plus, X } from 'lucide-vue-next';
import api from '../../lib/api';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppCard from '../../components/ui/AppCard.vue';
import AppIconButton from '../../components/ui/AppIconButton.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppSwitch from '../../components/ui/AppSwitch.vue';

const loading = ref(false);
const saving = ref(false);
const error = ref('');
const items = ref([]);

const dialogOpen = ref(false);
const editingId = ref(null);

const form = reactive({
    codigo: '',
    emailLocal: '',
    pin: '',
    nome: '',
    senha: '',
    senhaConfirmacao: '',
    perfil: 'operador',
    ativo: true,
});

const dialogTitle = computed(() => (editingId.value ? 'Editar Operador' : 'Novo Operador'));
const saveLabel = computed(() => (editingId.value ? 'Salvar' : 'Salvar'));
const emailDomain = '@simplespdv.local';
const localPartPattern = /^[a-zA-Z0-9._-]+$/;

function resetForm() {
    form.codigo = '';
    form.emailLocal = '';
    form.pin = '';
    form.nome = '';
    form.senha = '';
    form.senhaConfirmacao = '';
    form.perfil = 'operador';
    form.ativo = true;
}

function openCreate() {
    editingId.value = null;
    error.value = '';
    resetForm();
    dialogOpen.value = true;
}

function openEdit(item) {
    editingId.value = item.id;
    error.value = '';
    form.codigo = item.codigo;
    form.emailLocal = item.email_local || item.codigo;
    form.pin = '';
    form.nome = item.nome;
    form.senha = '';
    form.senhaConfirmacao = '';
    form.perfil = item.perfil;
    form.ativo = !!item.ativo;
    dialogOpen.value = true;
}

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get('/operators');
        items.value = data;
    } finally {
        loading.value = false;
    }
}

async function save() {
    const codigo = form.codigo.trim();
    const emailLocal = form.emailLocal.trim().toLowerCase();
    const nome = form.nome.trim();
    const pin = form.pin.trim();
    const senha = form.senha.trim();
    const senhaConfirmacao = form.senhaConfirmacao.trim();
    const hasPasswordChange = !!senha || !!senhaConfirmacao;

    if (!codigo || !nome || !emailLocal || (!editingId.value && !pin)) {
        error.value = 'Preencha os campos obrigatórios.';
        return;
    }

    if (!localPartPattern.test(codigo)) {
        error.value = 'Código inválido. Use apenas letras, números, ".", "_" ou "-".';
        return;
    }

    if (!localPartPattern.test(emailLocal)) {
        error.value = 'E-mail de acesso inválido. Use apenas letras, números, ".", "_" ou "-".';
        return;
    }

    if (!editingId.value && !senha) {
        error.value = 'Informe uma senha de acesso.';
        return;
    }

    if ((editingId.value && hasPasswordChange) || !editingId.value) {
        if (!senha || !senhaConfirmacao) {
            error.value = 'Preencha a senha e a confirmação.';
            return;
        }
        if (senha.length < 6) {
            error.value = 'A senha deve ter ao menos 6 caracteres.';
            return;
        }
        if (senha !== senhaConfirmacao) {
            error.value = 'A confirmação da senha não confere.';
            return;
        }
    }

    saving.value = true;
    error.value = '';

    const payload = {
        codigo,
        email_local: emailLocal,
        nome,
        perfil: form.perfil,
        ativo: form.ativo,
    };

    if (pin) {
        payload.pin = pin;
    }

    if (senha) {
        payload.password = senha;
        payload.password_confirmation = senhaConfirmacao;
    }

    try {
        if (editingId.value) {
            await api.put(`/operators/${editingId.value}`, payload);
        } else {
            await api.post('/operators', payload);
        }

        dialogOpen.value = false;
        await load();
    } catch (requestError) {
        error.value = requestError?.response?.data?.message ?? 'Falha ao salvar operador.';
    } finally {
        saving.value = false;
    }
}

function closeDialog() {
    if (saving.value) return;
    dialogOpen.value = false;
}

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <SettingsPageHeader title="Operadores" subtitle="Gerenciar operadores do PDV">
            <template #actions>
                <AppButton @click="openCreate">
                    <Plus class="h-4 w-4" aria-hidden="true" />
                    Novo Operador
                </AppButton>
            </template>
        </SettingsPageHeader>

        <AppCard v-if="loading" class="p-8 text-center text-muted">
            Carregando operadores...
        </AppCard>

        <div v-else class="ui-table-wrap operators-grid-shell">
            <table class="ui-table operators-table">
                <thead>
                    <tr>
                        <th class="operators-col-code">Código</th>
                        <th class="operators-col-name">Nome</th>
                        <th class="operators-col-profile">Perfil</th>
                        <th class="operators-col-status">Status</th>
                        <th class="operators-col-action">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in items" :key="item.id">
                        <td class="operators-cell-text">{{ item.codigo }}</td>
                        <td class="operators-cell-text">{{ item.nome }}</td>
                        <td>
                            <div class="operators-cell-badge">
                                <AppBadge :variant="item.perfil === 'admin' ? 'danger' : 'default'">
                                    {{ item.perfil === 'admin' ? 'Administrador' : 'Operador' }}
                                </AppBadge>
                            </div>
                        </td>
                        <td>
                            <div class="operators-cell-badge">
                                <AppBadge :variant="item.ativo ? 'success' : 'default'">
                                    {{ item.ativo ? 'Ativo' : 'Inativo' }}
                                </AppBadge>
                            </div>
                        </td>
                        <td>
                            <div class="operators-cell-action">
                                <AppIconButton title="Editar operador" @click="openEdit(item)">
                                    <Pencil class="h-4 w-4" aria-hidden="true" />
                                </AppIconButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="dialogOpen" class="ui-modal-backdrop" @click.self="closeDialog">
            <section class="operators-modal-panel">
                <header class="operators-modal-head">
                    <h3 class="operators-modal-title">{{ dialogTitle }}</h3>
                    <button type="button" class="operators-close-btn" @click="closeDialog">
                        <X class="h-5 w-5" aria-hidden="true" />
                    </button>
                </header>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <AppInput v-model="form.codigo" label="Código *" placeholder="Ex: op01" />
                    <AppInput
                        v-model="form.pin"
                        label="PIN *"
                        :placeholder="editingId ? 'Digite para alterar (6 dígitos)' : 'Ex: 123456'"
                        maxlength="6"
                        inputmode="numeric"
                    />

                    <AppInput
                        v-model="form.emailLocal"
                        label="E-mail de acesso *"
                        placeholder="usuario"
                        :hint="`Domínio fixo: ${emailDomain}`"
                        class="md:col-span-2"
                    />

                    <AppInput v-model="form.nome" label="Nome *" placeholder="Nome do operador" class="md:col-span-2" />

                    <AppInput
                        v-model="form.senha"
                        type="password"
                        :label="editingId ? 'Senha de acesso' : 'Senha de acesso *'"
                        :placeholder="editingId ? 'Preencha para alterar' : 'Digite a senha de acesso'"
                    />
                    <AppInput
                        v-model="form.senhaConfirmacao"
                        type="password"
                        :label="editingId ? 'Repita a senha' : 'Repita a senha *'"
                        :placeholder="editingId ? 'Repita para confirmar alteração' : 'Repita a senha'"
                    />

                    <AppSelect v-model="form.perfil" label="Perfil" class="md:col-span-2">
                        <option value="operador">Operador</option>
                        <option value="admin">Administrador</option>
                    </AppSelect>

                    <div class="md:col-span-2">
                        <AppSwitch v-model="form.ativo" label="Ativo" />
                    </div>
                </div>

                <p v-if="error" class="operators-error">{{ error }}</p>

                <footer class="operators-modal-actions">
                    <AppButton variant="secondary" @click="closeDialog">Cancelar</AppButton>
                    <AppButton :loading="saving" @click="save">{{ saveLabel }}</AppButton>
                </footer>
            </section>
        </div>
    </div>
</template>

<style scoped>
.operators-grid-shell {
    border-radius: var(--radius-xl);
}

.operators-table {
    table-layout: fixed;
}

.operators-col-code,
.operators-col-name,
.operators-col-profile,
.operators-col-status,
.operators-col-action {
    text-align: center;
}

.operators-col-code {
    width: 18%;
}

.operators-col-name {
    width: 30%;
}

.operators-col-profile {
    width: 20%;
}

.operators-col-status {
    width: 18%;
}

.operators-col-action {
    width: 14%;
}

.operators-cell-text {
    text-align: center;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--color-text);
}

.operators-cell-badge,
.operators-cell-action {
    display: flex;
    justify-content: center;
    align-items: center;
}

.operators-cell-action :deep(.ui-icon-btn) {
    width: 2.6rem;
    height: 2.6rem;
}

.operators-table th {
    text-transform: none;
    font-size: 1.05rem;
    font-weight: 600;
    color: var(--color-text-muted);
}

.operators-table td {
    font-size: 1.05rem;
    padding-top: 1.15rem;
    padding-bottom: 1.15rem;
}

.operators-modal-panel {
    width: min(40rem, 100%);
    border-radius: var(--radius-xl);
    border: 1px solid var(--color-border);
    background: var(--color-bg-surface);
    padding: var(--space-5);
    box-shadow: var(--shadow-lg);
}

.operators-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-3);
    margin-bottom: var(--space-4);
}

.operators-modal-title {
    margin: 0;
    font-size: 2rem;
    line-height: 1.1;
    font-weight: 700;
    color: var(--color-text);
}

.operators-close-btn {
    width: 2rem;
    height: 2rem;
    border-radius: 999px;
    border: 1px solid transparent;
    background: transparent;
    color: var(--color-text-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition-fast);
}

.operators-close-btn:hover {
    border-color: var(--color-border);
    background: color-mix(in srgb, var(--color-bg-elevated) 80%, var(--color-bg-surface));
    color: var(--color-text);
}

.operators-error {
    margin: 0.7rem 0 0;
    color: var(--color-danger);
    font-size: 0.86rem;
    font-weight: 600;
}

.operators-modal-actions {
    margin-top: var(--space-5);
    display: flex;
    justify-content: flex-end;
    gap: var(--space-2);
}

@media (max-width: 900px) {
    .operators-modal-title {
        font-size: 1.55rem;
    }

    .operators-table {
        table-layout: auto;
    }

    .operators-col-code,
    .operators-col-name,
    .operators-col-profile,
    .operators-col-status,
    .operators-col-action {
        width: auto;
    }
}
</style>
