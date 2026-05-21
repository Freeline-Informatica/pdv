<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../lib/api';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import AppCard from '../../components/ui/AppCard.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppModal from '../../components/ui/AppModal.vue';
import AppInput from '../../components/ui/AppInput.vue';
import AppTextarea from '../../components/ui/AppTextarea.vue';
import AppSelect from '../../components/ui/AppSelect.vue';
import AppCheckbox from '../../components/ui/AppCheckbox.vue';
import AppSearchField from '../../components/ui/AppSearchField.vue';

const router = useRouter();

const loading = ref(false);
const saving = ref(false);
const deletingId = ref('');
const modalOpen = ref(false);
const editingId = ref('');
const selectedFamilyId = ref('');
const error = ref('');
const submitError = ref('');

const search = ref('');
const onlyActive = ref(false);
const includeDeleted = ref(false);

const modeOptions = [
    { id: 'SEQUENCIAL_FAMILIA', label: 'SEQUENCIAL_FAMILIA' },
    { id: 'MANUAL', label: 'MANUAL' },
    { id: 'SEQUENCIAL_GLOBAL', label: 'SEQUENCIAL_GLOBAL' },
];

const families = ref([]);

const form = reactive({
    codigo: '',
    nome: '',
    codigo_prefixo: '',
    modo_geracao_codigo: 'SEQUENCIAL_FAMILIA',
    faixa_inicial: '',
    faixa_final: '',
    proximo_numero: '',
    descricao: '',
    ativo: true,
});

const formErrors = reactive({
    codigo: '',
    nome: '',
    codigo_prefixo: '',
    modo_geracao_codigo: '',
    faixa_inicial: '',
    faixa_final: '',
    proximo_numero: '',
    descricao: '',
    ativo: '',
});

const selectedFamily = computed(() => families.value.find((item) => item.id === selectedFamilyId.value) || null);

const totalCount = computed(() => families.value.length);
const activeCount = computed(() => families.value.filter((item) => item.ativo && !item.deleted_at).length);

function goBack() {
    router.push('/configuracoes/catalogo/parametros');
}

function normalizeText(value) {
    const cleaned = String(value || '').trim();
    return cleaned === '' ? null : cleaned;
}

function normalizeInteger(value) {
    const cleaned = String(value ?? '').trim();
    if (cleaned === '') return null;
    const parsed = Number(cleaned);
    return Number.isFinite(parsed) ? Math.trunc(parsed) : null;
}

function resetForm() {
    form.codigo = '';
    form.nome = '';
    form.codigo_prefixo = '';
    form.modo_geracao_codigo = 'SEQUENCIAL_FAMILIA';
    form.faixa_inicial = '';
    form.faixa_final = '';
    form.proximo_numero = '';
    form.descricao = '';
    form.ativo = true;

    Object.keys(formErrors).forEach((key) => {
        formErrors[key] = '';
    });
    submitError.value = '';
}

function fillFormFromFamily(item) {
    form.codigo = item?.codigo || '';
    form.nome = item?.nome || '';
    form.codigo_prefixo = item?.codigo_prefixo || '';
    form.modo_geracao_codigo = item?.modo_geracao_codigo || 'SEQUENCIAL_FAMILIA';
    form.faixa_inicial = item?.faixa_inicial == null ? '' : String(item.faixa_inicial);
    form.faixa_final = item?.faixa_final == null ? '' : String(item.faixa_final);
    form.proximo_numero = item?.proximo_numero == null ? '' : String(item.proximo_numero);
    form.descricao = item?.descricao || '';
    form.ativo = !!item?.ativo;
}

function openCreate() {
    editingId.value = '';
    resetForm();
    modalOpen.value = true;
}

function openEdit(item) {
    if (item?.deleted_at) return;
    editingId.value = item.id;
    resetForm();
    fillFormFromFamily(item);
    modalOpen.value = true;
}

function makeCloneCode(baseCode) {
    const prefix = String(baseCode || 'FAM').trim() || 'FAM';
    const base = `${prefix}-COPIA`;
    const existing = new Set(families.value.map((item) => String(item.codigo || '').toUpperCase()));

    if (!existing.has(base.toUpperCase())) {
        return base;
    }

    let index = 2;
    while (existing.has(`${base}-${index}`.toUpperCase())) {
        index += 1;
    }

    return `${base}-${index}`;
}

function cloneSelected() {
    if (!selectedFamily.value) return;

    editingId.value = '';
    resetForm();
    fillFormFromFamily(selectedFamily.value);
    form.codigo = makeCloneCode(selectedFamily.value.codigo);
    form.nome = `${selectedFamily.value.nome || ''} (cópia)`.trim();
    modalOpen.value = true;
}

function validateForm() {
    Object.keys(formErrors).forEach((key) => {
        formErrors[key] = '';
    });
    submitError.value = '';

    if (!String(form.codigo || '').trim()) {
        formErrors.codigo = 'Informe o código.';
    }

    if (!String(form.nome || '').trim()) {
        formErrors.nome = 'Informe o nome.';
    }

    const faixaInicial = normalizeInteger(form.faixa_inicial);
    const faixaFinal = normalizeInteger(form.faixa_final);
    const proximoNumero = normalizeInteger(form.proximo_numero);

    if (form.faixa_inicial !== '' && faixaInicial == null) {
        formErrors.faixa_inicial = 'Informe um número válido.';
    }

    if (form.faixa_final !== '' && faixaFinal == null) {
        formErrors.faixa_final = 'Informe um número válido.';
    }

    if (form.proximo_numero !== '' && proximoNumero == null) {
        formErrors.proximo_numero = 'Informe um número válido.';
    }

    if (faixaInicial != null && faixaFinal != null && faixaFinal < faixaInicial) {
        formErrors.faixa_final = 'A faixa final deve ser maior ou igual à faixa inicial.';
    }

    if (proximoNumero != null && faixaInicial != null && proximoNumero < faixaInicial) {
        formErrors.proximo_numero = 'O próximo número não pode ser menor que a faixa inicial.';
    }

    if (proximoNumero != null && faixaFinal != null && proximoNumero > faixaFinal) {
        formErrors.proximo_numero = 'O próximo número não pode ser maior que a faixa final.';
    }

    return Object.values(formErrors).every((value) => !value);
}

async function loadFamilies() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await api.get('/catalog/families', {
            params: {
                search: search.value || undefined,
                only_active: onlyActive.value || undefined,
                include_deleted: includeDeleted.value || undefined,
            },
        });

        families.value = Array.isArray(data) ? data : [];

        if (selectedFamilyId.value && !families.value.find((item) => item.id === selectedFamilyId.value)) {
            selectedFamilyId.value = '';
        }
    } catch (requestError) {
        error.value = requestError?.response?.data?.message ?? 'Falha ao carregar famílias.';
        families.value = [];
    } finally {
        loading.value = false;
    }
}

async function save() {
    if (!validateForm()) return;

    saving.value = true;
    submitError.value = '';

    try {
        const payload = {
            codigo: String(form.codigo || '').trim(),
            nome: String(form.nome || '').trim(),
            codigo_prefixo: normalizeText(form.codigo_prefixo),
            modo_geracao_codigo: normalizeText(form.modo_geracao_codigo),
            faixa_inicial: normalizeInteger(form.faixa_inicial),
            faixa_final: normalizeInteger(form.faixa_final),
            proximo_numero: normalizeInteger(form.proximo_numero),
            descricao: normalizeText(form.descricao),
            ativo: !!form.ativo,
        };

        if (editingId.value) {
            await api.put(`/catalog/families/${editingId.value}`, payload);
        } else {
            await api.post('/catalog/families', payload);
        }

        modalOpen.value = false;
        await loadFamilies();
    } catch (requestError) {
        const responseErrors = requestError?.response?.data?.errors || {};
        Object.entries(responseErrors).forEach(([field, messages]) => {
            if (!Object.hasOwn(formErrors, field)) return;
            formErrors[field] = Array.isArray(messages) && messages.length ? String(messages[0]) : 'Campo inválido.';
        });

        submitError.value = requestError?.response?.data?.message ?? 'Não foi possível salvar a família.';
    } finally {
        saving.value = false;
    }
}

async function removeFamily(item) {
    if (!item || item.deleted_at) return;
    if (!window.confirm(`Excluir família "${item.nome}"?`)) return;

    deletingId.value = item.id;
    try {
        await api.delete(`/catalog/families/${item.id}`);
        await loadFamilies();
    } catch (requestError) {
        error.value = requestError?.response?.data?.message ?? 'Não foi possível excluir a família.';
    } finally {
        deletingId.value = '';
    }
}

onMounted(loadFamilies);
</script>

<template>
    <div class="space-y-4 family-catalog-view">
        <p class="family-breadcrumb">Catálogo &gt; Parâmetros</p>

        <SettingsPageHeader
            title="Famílias de Produto"
            subtitle="Cadastro usado para organização comercial e regras de código operacional."
        >
            <template #actions>
                <div class="family-header-actions">
                    <AppButton variant="secondary" @click="goBack">Voltar aos parâmetros</AppButton>
                    <AppButton variant="secondary" :disabled="!selectedFamily || !!selectedFamily?.deleted_at" @click="cloneSelected">
                        Clonar família cadastrada
                    </AppButton>
                    <AppButton @click="openCreate">Nova família</AppButton>
                </div>
            </template>
        </SettingsPageHeader>

        <AppCard class="family-notice">
            O cadastro principal de produtos já consome este catálogo. Use esta tela para manter regras de família sem depender do modal rápido.
        </AppCard>

        <AppCard elevated class="family-grid-shell">
            <div class="family-grid-header">
                <div>
                    <h3 class="family-grid-title">Famílias cadastradas</h3>
                    <p class="family-grid-subtitle">{{ totalCount }} registro(s) • {{ activeCount }} ativo(s)</p>
                </div>
            </div>

            <section class="family-filters">
                <p class="family-filters-label">Filtros</p>
                <div class="family-filters-row">
                    <div class="family-search">
                        <AppSearchField v-model="search" placeholder="Buscar por código, nome ou prefixo" />
                    </div>
                    <div class="family-filter-checks">
                        <AppCheckbox v-model="onlyActive" label="Somente ativos" />
                        <AppCheckbox v-model="includeDeleted" label="Incluir excluídos" />
                    </div>
                    <AppButton variant="secondary" :loading="loading" @click="loadFamilies">Atualizar</AppButton>
                </div>
            </section>

            <p v-if="error" class="text-danger text-sm">{{ error }}</p>

            <div v-if="loading" class="family-loading">Carregando famílias...</div>

            <div v-else-if="families.length === 0" class="family-empty">
                Nenhuma família encontrada para os filtros selecionados.
            </div>

            <div v-else class="family-list">
                <article
                    v-for="item in families"
                    :key="item.id"
                    class="family-item-card"
                    :class="{
                        'is-selected': selectedFamilyId === item.id,
                        'is-deleted': !!item.deleted_at,
                    }"
                    @click="selectedFamilyId = item.id"
                >
                    <div class="family-item-main">
                        <div class="family-item-title-row">
                            <h4 class="family-item-title">{{ item.codigo }} · {{ item.nome }}</h4>
                            <div class="family-item-badges">
                                <AppBadge variant="default">{{ item.modo_geracao_codigo || '—' }}</AppBadge>
                                <AppBadge :variant="item.deleted_at ? 'warning' : item.ativo ? 'success' : 'default'">
                                    {{ item.deleted_at ? 'Excluído' : item.ativo ? 'Ativo' : 'Inativo' }}
                                </AppBadge>
                            </div>
                        </div>
                        <p class="family-item-description">{{ item.descricao || 'Sem descrição complementar.' }}</p>
                        <div class="family-item-meta">
                            <span>Prefixo: {{ item.codigo_prefixo || '—' }}</span>
                            <span>Faixa inicial: {{ item.faixa_inicial ?? '—' }}</span>
                            <span>Faixa final: {{ item.faixa_final ?? '—' }}</span>
                            <span>Próximo número: {{ item.proximo_numero ?? '—' }}</span>
                        </div>
                    </div>

                    <div class="family-item-actions">
                        <AppButton
                            variant="secondary"
                            :disabled="!!item.deleted_at"
                            @click.stop="openEdit(item)"
                        >
                            Editar
                        </AppButton>
                        <AppButton
                            variant="danger"
                            :disabled="!!item.deleted_at || deletingId === item.id"
                            @click.stop="removeFamily(item)"
                        >
                            Excluir
                        </AppButton>
                    </div>
                </article>
            </div>
        </AppCard>

        <AppModal
            :open="modalOpen"
            :title="editingId ? 'Editar família' : 'Nova família'"
            width-class="family-modal-width"
            @close="modalOpen = false"
        >
            <div class="family-form-grid">
                <AppInput v-model="form.codigo" label="Código" :error="formErrors.codigo" maxlength="30" />
                <AppInput v-model="form.nome" label="Nome" :error="formErrors.nome" maxlength="120" />

                <AppInput v-model="form.codigo_prefixo" label="Prefixo" :error="formErrors.codigo_prefixo" maxlength="30" />
                <AppSelect v-model="form.modo_geracao_codigo" label="Modo de geração" :error="formErrors.modo_geracao_codigo">
                    <option v-for="option in modeOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                </AppSelect>

                <AppInput v-model="form.faixa_inicial" label="Faixa inicial" :error="formErrors.faixa_inicial" inputmode="numeric" />
                <AppInput v-model="form.faixa_final" label="Faixa final" :error="formErrors.faixa_final" inputmode="numeric" />

                <AppInput v-model="form.proximo_numero" label="Próximo número" :error="formErrors.proximo_numero" inputmode="numeric" />
                <div class="family-checkbox-wrap">
                    <AppCheckbox v-model="form.ativo" label="Registro ativo" />
                </div>

                <div class="family-form-description">
                    <AppTextarea
                        v-model="form.descricao"
                        label="Descrição"
                        :error="formErrors.descricao"
                        rows="4"
                        maxlength="255"
                    />
                </div>
            </div>

            <p v-if="submitError" class="text-danger text-sm mt-3">{{ submitError }}</p>

            <div class="family-form-actions">
                <AppButton variant="secondary" @click="modalOpen = false">Cancelar</AppButton>
                <AppButton :loading="saving" @click="save">Salvar</AppButton>
            </div>
        </AppModal>
    </div>
</template>

<style scoped>
.family-breadcrumb {
    margin: 0;
    font-size: 0.82rem;
    font-weight: 700;
    color: color-mix(in srgb, var(--color-text-muted) 86%, transparent);
}

.family-header-actions {
    display: inline-flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.6rem;
}

.family-notice {
    color: var(--color-text);
}

.family-grid-shell {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.family-grid-title {
    margin: 0;
    font-size: 2rem;
    line-height: 1.1;
    font-weight: 900;
    color: var(--color-text);
}

.family-grid-subtitle {
    margin: 0.35rem 0 0;
    color: var(--color-text-muted);
}

.family-filters {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.family-filters-label {
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-size: 0.76rem;
    color: color-mix(in srgb, var(--color-text-muted) 80%, transparent);
    font-weight: 800;
}

.family-filters-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 0.75rem;
}

.family-search {
    min-width: 0;
}

.family-filter-checks {
    display: inline-flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.85rem;
}

.family-loading,
.family-empty {
    border: 1px dashed color-mix(in srgb, var(--color-border-strong) 45%, transparent);
    border-radius: var(--radius-md);
    padding: 1rem;
    color: var(--color-text-muted);
    text-align: center;
}

.family-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.family-item-card {
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 42%, transparent);
    border-radius: var(--radius-md);
    padding: 1rem;
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 1rem;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.family-item-card:hover {
    border-color: color-mix(in srgb, var(--color-primary) 40%, transparent);
}

.family-item-card.is-selected {
    border-color: color-mix(in srgb, var(--color-primary) 58%, transparent);
    background: color-mix(in srgb, var(--color-primary) 10%, var(--color-bg-surface));
}

.family-item-card.is-deleted {
    opacity: 0.75;
}

.family-item-title-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.6rem;
}

.family-item-title {
    margin: 0;
    font-size: 1.1rem;
    color: var(--color-text);
}

.family-item-badges {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}

.family-item-description {
    margin: 0.75rem 0 0;
    color: var(--color-text-muted);
}

.family-item-meta {
    margin-top: 0.7rem;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(8rem, 1fr));
    gap: 0.4rem 0.8rem;
    color: var(--color-text-muted);
}

.family-item-actions {
    display: inline-flex;
    justify-content: flex-end;
    align-items: flex-start;
    gap: 0.5rem;
}

:deep(.family-modal-width) {
    width: min(960px, calc(100vw - 2.5rem));
}

.family-form-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 0.9rem;
}

.family-checkbox-wrap {
    display: flex;
    align-items: flex-end;
    min-height: 100%;
}

.family-form-description {
    grid-column: 1 / -1;
}

.family-form-actions {
    margin-top: 1rem;
    display: inline-flex;
    width: 100%;
    justify-content: flex-end;
    gap: 0.6rem;
}

@media (min-width: 900px) {
    .family-filters-row {
        grid-template-columns: minmax(0, 1fr) auto auto;
        align-items: end;
    }

    .family-item-card {
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: flex-start;
    }

    .family-form-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
