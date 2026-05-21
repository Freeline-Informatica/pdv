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
import AppSelect from '../../components/ui/AppSelect.vue';
import AppCheckbox from '../../components/ui/AppCheckbox.vue';
import AppSearchField from '../../components/ui/AppSearchField.vue';

const router = useRouter();

const loading = ref(false);
const saving = ref(false);
const deletingId = ref('');
const modalOpen = ref(false);
const editingId = ref('');
const selectedUnitId = ref('');
const error = ref('');
const submitError = ref('');

const search = ref('');
const onlyActive = ref(false);
const includeDeleted = ref(false);

const articleOptions = [
    { id: '', label: 'Sem artigo' },
    { id: 'o', label: 'o' },
    { id: 'a', label: 'a' },
    { id: 'os', label: 'os' },
    { id: 'as', label: 'as' },
];

const units = ref([]);

const form = reactive({
    unidade: '',
    descricao: '',
    descricao_plural: '',
    artigo: '',
    codigo_fiscal: '',
    decimais: '0',
    status: true,
});

const formErrors = reactive({
    unidade: '',
    descricao: '',
    descricao_plural: '',
    artigo: '',
    codigo_fiscal: '',
    decimais: '',
    status: '',
});

const selectedUnit = computed(() => units.value.find((item) => item.id === selectedUnitId.value) || null);

const totalCount = computed(() => units.value.length);
const activeCount = computed(() => units.value.filter((item) => item.status && !item.deleted_at).length);

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
    form.unidade = '';
    form.descricao = '';
    form.descricao_plural = '';
    form.artigo = '';
    form.codigo_fiscal = '';
    form.decimais = '0';
    form.status = true;

    Object.keys(formErrors).forEach((key) => {
        formErrors[key] = '';
    });
    submitError.value = '';
}

function fillFormFromUnit(item) {
    form.unidade = item?.unidade || '';
    form.descricao = item?.descricao || '';
    form.descricao_plural = item?.descricao_plural || '';
    form.artigo = item?.artigo || '';
    form.codigo_fiscal = item?.codigo_fiscal || '';
    form.decimais = item?.decimais == null ? '0' : String(item.decimais);
    form.status = !!item?.status;
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
    fillFormFromUnit(item);
    modalOpen.value = true;
}

function makeCloneUnitCode(baseCode) {
    const source = String(baseCode || 'UN').trim().toUpperCase() || 'UN';
    const base = `${source}_COP`;
    const existing = new Set(units.value.map((item) => String(item.unidade || '').toUpperCase()));

    if (!existing.has(base.toUpperCase()) && base.length <= 20) {
        return base;
    }

    let index = 2;
    while (index < 1000) {
        const candidate = `${source}_COP${index}`;
        if (candidate.length <= 20 && !existing.has(candidate.toUpperCase())) {
            return candidate;
        }
        index += 1;
    }

    return `UN_COPIA_${Date.now()}`.slice(0, 20);
}

function cloneSelected() {
    if (!selectedUnit.value) return;

    editingId.value = '';
    resetForm();
    fillFormFromUnit(selectedUnit.value);
    form.unidade = makeCloneUnitCode(selectedUnit.value.unidade);
    form.descricao = `${selectedUnit.value.descricao || ''} (cópia)`.trim();
    modalOpen.value = true;
}

function validateForm() {
    Object.keys(formErrors).forEach((key) => {
        formErrors[key] = '';
    });
    submitError.value = '';

    if (!String(form.unidade || '').trim()) {
        formErrors.unidade = 'Informe o código.';
    }

    if (!String(form.descricao || '').trim()) {
        formErrors.descricao = 'Informe a descrição.';
    }

    const decimais = normalizeInteger(form.decimais);
    if (decimais == null) {
        formErrors.decimais = 'Informe as casas decimais.';
    } else if (decimais < 0 || decimais > 6) {
        formErrors.decimais = 'Informe um valor entre 0 e 6.';
    }

    return Object.values(formErrors).every((value) => !value);
}

async function loadUnits() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await api.get('/catalog/units', {
            params: {
                search: search.value || undefined,
                only_active: onlyActive.value || undefined,
                include_deleted: includeDeleted.value || undefined,
            },
        });

        units.value = Array.isArray(data) ? data : [];

        if (selectedUnitId.value && !units.value.find((item) => item.id === selectedUnitId.value)) {
            selectedUnitId.value = '';
        }
    } catch (requestError) {
        error.value = requestError?.response?.data?.message ?? 'Falha ao carregar unidades.';
        units.value = [];
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
            unidade: String(form.unidade || '').trim().toUpperCase(),
            descricao: String(form.descricao || '').trim(),
            descricao_plural: normalizeText(form.descricao_plural),
            artigo: normalizeText(form.artigo),
            codigo_fiscal: normalizeText(form.codigo_fiscal),
            decimais: normalizeInteger(form.decimais) ?? 0,
            status: !!form.status,
        };

        if (editingId.value) {
            await api.put(`/catalog/units/${editingId.value}`, payload);
        } else {
            await api.post('/catalog/units', payload);
        }

        modalOpen.value = false;
        await loadUnits();
    } catch (requestError) {
        const responseErrors = requestError?.response?.data?.errors || {};
        Object.entries(responseErrors).forEach(([field, messages]) => {
            if (!Object.hasOwn(formErrors, field)) return;
            formErrors[field] = Array.isArray(messages) && messages.length ? String(messages[0]) : 'Campo inválido.';
        });

        submitError.value = requestError?.response?.data?.message ?? 'Não foi possível salvar a unidade.';
    } finally {
        saving.value = false;
    }
}

async function removeUnit(item) {
    if (!item || item.deleted_at) return;
    if (!window.confirm(`Excluir unidade "${item.unidade}"?`)) return;

    deletingId.value = item.id;
    try {
        await api.delete(`/catalog/units/${item.id}`);
        await loadUnits();
    } catch (requestError) {
        error.value = requestError?.response?.data?.message ?? 'Não foi possível excluir a unidade.';
    } finally {
        deletingId.value = '';
    }
}

onMounted(loadUnits);
</script>

<template>
    <div class="space-y-4 units-catalog-view">
        <p class="units-breadcrumb">Catálogo &gt; Parâmetros</p>

        <SettingsPageHeader
            title="Unidades de Medida"
            subtitle="Catálogo usado pelo produto, fornecedor, conversões comerciais e leitura fiscal."
        >
            <template #actions>
                <div class="units-header-actions">
                    <AppButton variant="secondary" @click="goBack">Voltar aos parâmetros</AppButton>
                    <AppButton variant="secondary" :disabled="!selectedUnit || !!selectedUnit?.deleted_at" @click="cloneSelected">
                        Clonar unidade cadastrada
                    </AppButton>
                    <AppButton @click="openCreate">Nova unidade</AppButton>
                </div>
            </template>
        </SettingsPageHeader>

        <AppCard class="units-notice">
            Esta tela complementa o modal rápido do cadastro de produto com manutenção completa, busca e restauração.
        </AppCard>

        <AppCard elevated class="units-grid-shell">
            <div class="units-grid-header">
                <div>
                    <h3 class="units-grid-title">Unidades cadastradas</h3>
                    <p class="units-grid-subtitle">{{ totalCount }} registro(s) • {{ activeCount }} ativo(s)</p>
                </div>
            </div>

            <section class="units-filters">
                <p class="units-filters-label">Filtros</p>
                <div class="units-filters-row">
                    <div class="units-search">
                        <AppSearchField v-model="search" placeholder="Buscar por unidade, descrição ou código fiscal" />
                    </div>
                    <div class="units-filter-checks">
                        <AppCheckbox v-model="onlyActive" label="Somente ativas" />
                        <AppCheckbox v-model="includeDeleted" label="Incluir excluídas" />
                    </div>
                    <AppButton variant="secondary" :loading="loading" @click="loadUnits">Atualizar</AppButton>
                </div>
            </section>

            <p v-if="error" class="text-danger text-sm">{{ error }}</p>

            <div v-if="loading" class="units-loading">Carregando unidades...</div>

            <div v-else-if="units.length === 0" class="units-empty">
                Nenhuma unidade encontrada para os filtros selecionados.
            </div>

            <div v-else class="units-list">
                <article
                    v-for="item in units"
                    :key="item.id"
                    class="units-item-card"
                    :class="{
                        'is-selected': selectedUnitId === item.id,
                        'is-deleted': !!item.deleted_at,
                    }"
                    @click="selectedUnitId = item.id"
                >
                    <div class="units-item-main">
                        <div class="units-item-title-row">
                            <h4 class="units-item-title">{{ item.unidade }} · {{ item.descricao }}</h4>
                            <div class="units-item-badges">
                                <AppBadge variant="default">{{ item.codigo_fiscal || item.unidade || '—' }}</AppBadge>
                                <AppBadge :variant="item.deleted_at ? 'warning' : item.status ? 'success' : 'default'">
                                    {{ item.deleted_at ? 'Excluída' : item.status ? 'Ativa' : 'Inativa' }}
                                </AppBadge>
                            </div>
                        </div>
                        <div class="units-item-meta">
                            <span>Plural: {{ item.descricao_plural || '—' }}</span>
                            <span>Artigo: {{ item.artigo || '—' }}</span>
                            <span>Decimais: {{ item.decimais ?? 0 }}</span>
                        </div>
                    </div>

                    <div class="units-item-actions">
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
                            @click.stop="removeUnit(item)"
                        >
                            Excluir
                        </AppButton>
                    </div>
                </article>
            </div>
        </AppCard>

        <AppModal
            :open="modalOpen"
            :title="editingId ? 'Editar unidade de medida' : 'Nova unidade de medida'"
            width-class="units-modal-width"
            @close="modalOpen = false"
        >
            <div class="units-form-grid">
                <AppInput v-model="form.unidade" label="Código" :error="formErrors.unidade" maxlength="20" />
                <AppInput v-model="form.descricao" label="Descrição" :error="formErrors.descricao" maxlength="120" />

                <AppInput v-model="form.descricao_plural" label="Descrição Plural" :error="formErrors.descricao_plural" maxlength="120" />
                <AppSelect v-model="form.artigo" label="Artigo" :error="formErrors.artigo">
                    <option v-for="option in articleOptions" :key="option.id || 'none'" :value="option.id">
                        {{ option.label }}
                    </option>
                </AppSelect>

                <AppInput v-model="form.codigo_fiscal" label="Código Fiscal" :error="formErrors.codigo_fiscal" maxlength="30" />
                <AppInput v-model="form.decimais" label="Casas decimais" :error="formErrors.decimais" inputmode="numeric" />

                <div class="units-checkbox-wrap">
                    <AppCheckbox v-model="form.status" label="Registro ativo" />
                </div>
            </div>

            <p v-if="submitError" class="text-danger text-sm mt-3">{{ submitError }}</p>

            <div class="units-form-actions">
                <AppButton variant="secondary" @click="modalOpen = false">Cancelar</AppButton>
                <AppButton :loading="saving" @click="save">Salvar</AppButton>
            </div>
        </AppModal>
    </div>
</template>

<style scoped>
.units-breadcrumb {
    margin: 0;
    font-size: 0.82rem;
    font-weight: 700;
    color: color-mix(in srgb, var(--color-text-muted) 86%, transparent);
}

.units-header-actions {
    display: inline-flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.6rem;
}

.units-notice {
    color: var(--color-text);
}

.units-grid-shell {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.units-grid-title {
    margin: 0;
    font-size: 2rem;
    line-height: 1.1;
    font-weight: 900;
    color: var(--color-text);
}

.units-grid-subtitle {
    margin: 0.35rem 0 0;
    color: var(--color-text-muted);
}

.units-filters {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.units-filters-label {
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-size: 0.76rem;
    color: color-mix(in srgb, var(--color-text-muted) 80%, transparent);
    font-weight: 800;
}

.units-filters-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 0.75rem;
}

.units-search {
    min-width: 0;
}

.units-filter-checks {
    display: inline-flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.85rem;
}

.units-loading,
.units-empty {
    border: 1px dashed color-mix(in srgb, var(--color-border-strong) 45%, transparent);
    border-radius: var(--radius-md);
    padding: 1rem;
    color: var(--color-text-muted);
    text-align: center;
}

.units-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.units-item-card {
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 42%, transparent);
    border-radius: var(--radius-md);
    padding: 1rem;
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 1rem;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.units-item-card:hover {
    border-color: color-mix(in srgb, var(--color-primary) 40%, transparent);
}

.units-item-card.is-selected {
    border-color: color-mix(in srgb, var(--color-primary) 58%, transparent);
    background: color-mix(in srgb, var(--color-primary) 10%, var(--color-bg-surface));
}

.units-item-card.is-deleted {
    opacity: 0.75;
}

.units-item-title-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.6rem;
}

.units-item-title {
    margin: 0;
    font-size: 1.1rem;
    color: var(--color-text);
}

.units-item-badges {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}

.units-item-meta {
    margin-top: 0.7rem;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(8rem, 1fr));
    gap: 0.4rem 0.8rem;
    color: var(--color-text-muted);
}

.units-item-actions {
    display: inline-flex;
    justify-content: flex-end;
    align-items: flex-start;
    gap: 0.5rem;
}

:deep(.units-modal-width) {
    width: min(960px, calc(100vw - 2.5rem));
}

.units-form-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 0.9rem;
}

.units-checkbox-wrap {
    display: flex;
    align-items: flex-end;
    min-height: 100%;
}

.units-form-actions {
    margin-top: 1rem;
    display: inline-flex;
    width: 100%;
    justify-content: flex-end;
    gap: 0.6rem;
}

@media (min-width: 900px) {
    .units-filters-row {
        grid-template-columns: minmax(0, 1fr) auto auto;
        align-items: end;
    }

    .units-item-card {
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: flex-start;
    }

    .units-form-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
