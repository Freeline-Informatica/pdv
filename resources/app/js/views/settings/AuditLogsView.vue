<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { FileText, RefreshCcw } from 'lucide-vue-next';
import api from '../../lib/api';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppCard from '../../components/ui/AppCard.vue';
import AppSearchField from '../../components/ui/AppSearchField.vue';
import AppSelect from '../../components/ui/AppSelect.vue';

const loading = ref(false);
const refreshing = ref(false);
const error = ref('');
const logs = ref([]);
const actionOptions = ref([]);
const entityOptions = ref([]);

const search = ref('');
const selectedAction = ref('');
const selectedEntity = ref('');

const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
});

let searchTimer = null;

const hasFilters = computed(() => search.value.trim() !== '' || selectedAction.value !== '' || selectedEntity.value !== '');
const canGoPrevious = computed(() => pagination.value.current_page > 1);
const canGoNext = computed(() => pagination.value.current_page < pagination.value.last_page);

function formatDateTime(value) {
    if (!value) return '—';

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(new Date(value));
}

function actionTone(actionKey) {
    if (actionKey.includes('login')) return 'is-login';
    if (actionKey.includes('logout')) return 'is-logout';
    if (actionKey.includes('authorize')) return 'is-auth';
    if (actionKey.includes('.create')) return 'is-create';
    if (actionKey.includes('.delete')) return 'is-delete';
    return 'is-update';
}

function operatorText(item) {
    const name = item?.operator?.nome || 'Sistema';
    const code = item?.operator?.codigo;
    return code ? `${name} (${code})` : name;
}

async function load({ keepSpinner = false } = {}) {
    if (!keepSpinner) {
        loading.value = true;
    }

    error.value = '';

    try {
        const params = {
            page: pagination.value.current_page,
            per_page: pagination.value.per_page,
        };

        if (search.value.trim()) params.search = search.value.trim();
        if (selectedAction.value) params.action = selectedAction.value;
        if (selectedEntity.value) params.entity = selectedEntity.value;

        const { data } = await api.get('/audit-logs', { params });
        logs.value = Array.isArray(data?.data) ? data.data : [];
        actionOptions.value = Array.isArray(data?.filters?.actions) ? data.filters.actions : [];
        entityOptions.value = Array.isArray(data?.filters?.entities) ? data.filters.entities : [];
        pagination.value = {
            ...pagination.value,
            ...data?.meta,
        };
    } catch (requestError) {
        error.value = requestError?.response?.data?.message ?? 'Falha ao carregar logs de auditoria.';
    } finally {
        loading.value = false;
        refreshing.value = false;
    }
}

function refresh() {
    refreshing.value = true;
    load({ keepSpinner: true });
}

function clearFilters() {
    search.value = '';
    selectedAction.value = '';
    selectedEntity.value = '';
    pagination.value.current_page = 1;
    load();
}

function goPrevious() {
    if (!canGoPrevious.value) return;
    pagination.value.current_page -= 1;
    load();
}

function goNext() {
    if (!canGoNext.value) return;
    pagination.value.current_page += 1;
    load();
}

watch([selectedAction, selectedEntity], () => {
    pagination.value.current_page = 1;
    load();
});

watch(search, () => {
    pagination.value.current_page = 1;
    if (searchTimer) {
        window.clearTimeout(searchTimer);
    }

    searchTimer = window.setTimeout(() => {
        load();
    }, 280);
});

onMounted(load);

onBeforeUnmount(() => {
    if (searchTimer) {
        window.clearTimeout(searchTimer);
        searchTimer = null;
    }
});
</script>

<template>
    <div class="space-y-4">
        <SettingsPageHeader title="Logs de Auditoria" subtitle="Registro de todas as ações do sistema" />

        <AppCard class="audit-shell p-4">
            <div class="audit-shell__head">
                <div class="audit-shell__title">
                    <span class="audit-shell__title-icon">
                        <FileText class="h-4 w-4" aria-hidden="true" />
                    </span>
                    <h2 class="audit-shell__title-text">Registros</h2>
                    <span class="audit-shell__counter">{{ pagination.total }}</span>
                </div>

                <div class="audit-shell__controls">
                    <div class="audit-shell__control search">
                        <AppSearchField v-model="search" placeholder="Buscar nos logs..." />
                    </div>

                    <div class="audit-shell__control select">
                        <AppSelect v-model="selectedAction">
                            <option value="">Todas as ações</option>
                            <option v-for="action in actionOptions" :key="action.key" :value="action.key">
                                {{ action.label }}
                            </option>
                        </AppSelect>
                    </div>

                    <div class="audit-shell__control select">
                        <AppSelect v-model="selectedEntity">
                            <option value="">Todas as entidades</option>
                            <option v-for="entity in entityOptions" :key="entity" :value="entity">
                                {{ entity }}
                            </option>
                        </AppSelect>
                    </div>

                    <AppButton variant="secondary" :loading="refreshing" @click="refresh">
                        <RefreshCcw class="h-4 w-4" aria-hidden="true" />
                        Atualizar
                    </AppButton>
                </div>
            </div>

            <div class="ui-table-wrap audit-shell__table">
                <table class="ui-table audit-table">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Ação</th>
                            <th>Entidade</th>
                            <th>Operador</th>
                            <th>Detalhes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="5" class="audit-table__empty">Carregando registros...</td>
                        </tr>
                        <tr v-else-if="error">
                            <td colspan="5" class="audit-table__empty audit-table__empty--error">{{ error }}</td>
                        </tr>
                        <tr v-else-if="logs.length === 0">
                            <td colspan="5" class="audit-table__empty">Nenhum log encontrado para os filtros aplicados.</td>
                        </tr>
                        <tr v-for="item in logs" :key="item.id">
                            <td class="audit-table__date">{{ formatDateTime(item.created_at) }}</td>
                            <td>
                                <span class="audit-action-pill" :class="actionTone(item.action_key)">
                                    {{ item.action_label }}
                                </span>
                            </td>
                            <td class="audit-table__entity">{{ item.entity }}</td>
                            <td class="audit-table__operator">{{ operatorText(item) }}</td>
                            <td class="audit-table__details">{{ item.details || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <footer class="audit-shell__footer">
                <div class="audit-shell__footer-left">
                    <p class="audit-shell__page-indicator">
                        Página {{ pagination.current_page }} de {{ pagination.last_page }}
                    </p>
                    <button v-if="hasFilters" type="button" class="audit-shell__clear-btn" @click="clearFilters">
                        Limpar filtros
                    </button>
                </div>

                <div class="audit-shell__footer-actions">
                    <AppButton variant="secondary" :disabled="!canGoPrevious || loading" @click="goPrevious">
                        Anterior
                    </AppButton>
                    <AppButton variant="secondary" :disabled="!canGoNext || loading" @click="goNext">
                        Próxima
                    </AppButton>
                </div>
            </footer>
        </AppCard>
    </div>
</template>

<style scoped>
.audit-shell {
    display: grid;
    gap: 0.85rem;
}

.audit-shell__head {
    display: flex;
    gap: 0.8rem;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}

.audit-shell__title {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
}

.audit-shell__title-icon {
    width: 2rem;
    height: 2rem;
    border-radius: 0.6rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text-muted);
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 82%, transparent);
    background: color-mix(in srgb, var(--color-bg-elevated) 72%, var(--color-bg-surface));
}

.audit-shell__title-text {
    margin: 0;
    font-size: 1.36rem;
    line-height: 1.15;
    font-weight: 800;
}

.audit-shell__counter {
    border-radius: 999px;
    padding: 0.2rem 0.72rem;
    font-size: 0.82rem;
    line-height: 1.2;
    font-weight: 800;
    color: var(--color-text);
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 82%, transparent);
    background: color-mix(in srgb, var(--color-bg-elevated) 70%, var(--color-bg-surface));
}

.audit-shell__controls {
    display: grid;
    grid-template-columns: minmax(14rem, 1fr) minmax(10rem, 12.5rem) minmax(10rem, 12.5rem) auto;
    gap: 0.62rem;
    align-items: center;
    margin-left: auto;
    flex: 1 1 45rem;
}

.audit-shell__control {
    min-width: 0;
}

.audit-shell__control.search {
    width: 100%;
}

.audit-shell__control.select {
    width: 100%;
}

.audit-shell__control :deep(.ui-field-wrap) {
    gap: 0;
}

.audit-shell__control :deep(.ui-field) {
    min-height: 2.45rem;
}

.audit-shell__controls :deep(.ui-btn) {
    white-space: nowrap;
}

.audit-shell__table {
    overflow-x: auto;
}

.audit-table {
    min-width: 64rem;
}

.audit-table th {
    text-align: left;
}

.audit-table__date {
    white-space: nowrap;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
}

.audit-table__entity,
.audit-table__operator {
    white-space: nowrap;
}

.audit-table__details {
    min-width: 16rem;
    color: var(--color-text-muted);
}

.audit-table__empty {
    text-align: center;
    padding-block: 1.5rem;
    color: var(--color-text-muted);
}

.audit-table__empty--error {
    color: var(--color-danger);
}

.audit-action-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    border: 1px solid transparent;
    padding: 0.22rem 0.62rem;
    font-size: 0.78rem;
    font-weight: 800;
    line-height: 1.2;
    white-space: nowrap;
}

.audit-action-pill.is-login {
    background: color-mix(in srgb, var(--color-primary) 22%, var(--color-bg-surface));
    border-color: color-mix(in srgb, var(--color-primary) 42%, transparent);
    color: var(--color-primary);
}

.audit-action-pill.is-logout {
    background: color-mix(in srgb, var(--color-border) 54%, var(--color-bg-surface));
    border-color: color-mix(in srgb, var(--color-border-strong) 65%, transparent);
    color: var(--color-text-muted);
}

.audit-action-pill.is-auth {
    background: color-mix(in srgb, var(--color-warning) 18%, var(--color-bg-surface));
    border-color: color-mix(in srgb, var(--color-warning) 34%, transparent);
    color: var(--color-warning);
}

.audit-action-pill.is-create {
    background: color-mix(in srgb, var(--color-success) 18%, var(--color-bg-surface));
    border-color: color-mix(in srgb, var(--color-success) 34%, transparent);
    color: var(--color-success);
}

.audit-action-pill.is-update {
    background: color-mix(in srgb, var(--color-primary) 16%, var(--color-bg-surface));
    border-color: color-mix(in srgb, var(--color-primary) 30%, transparent);
    color: color-mix(in srgb, var(--color-primary) 90%, var(--color-text));
}

.audit-action-pill.is-delete {
    background: color-mix(in srgb, var(--color-danger) 16%, var(--color-bg-surface));
    border-color: color-mix(in srgb, var(--color-danger) 30%, transparent);
    color: var(--color-danger);
}

.audit-shell__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
    flex-wrap: wrap;
}

.audit-shell__footer-left {
    display: inline-flex;
    gap: 0.75rem;
    align-items: center;
}

.audit-shell__page-indicator {
    margin: 0;
    font-size: 0.84rem;
    color: var(--color-text-muted);
}

.audit-shell__clear-btn {
    appearance: none;
    border: 0;
    padding: 0;
    background: transparent;
    color: var(--color-primary);
    font-size: 0.84rem;
    font-weight: 700;
    cursor: pointer;
}

.audit-shell__clear-btn:hover {
    text-decoration: underline;
}

.audit-shell__footer-actions {
    display: inline-flex;
    gap: 0.52rem;
}

@media (max-width: 960px) {
    .audit-shell__controls {
        grid-template-columns: 1fr;
        width: 100%;
        margin-left: 0;
        flex: 1 1 auto;
    }
}

@media (max-width: 1280px) and (min-width: 961px) {
    .audit-shell__controls {
        grid-template-columns: minmax(14rem, 1fr) minmax(10rem, 12.5rem) auto;
        width: 100%;
    }

    .audit-shell__control.search {
        grid-column: 1 / -1;
    }
}
</style>
