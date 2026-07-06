<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Download, FileText, RefreshCw, TableProperties } from 'lucide-vue-next';
import api from '../../lib/api';
import { buildMenuFiscalPayload, menuFiscalFiles, resolveMenuFiscalRequestMessage } from '../../lib/menuFiscal';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';
import SettingsFilterBar from '../../components/settings/SettingsFilterBar.vue';
import AppBadge from '../../components/ui/AppBadge.vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppCard from '../../components/ui/AppCard.vue';
import AppInput from '../../components/ui/AppInput.vue';

const today = new Date();
const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);

const filters = reactive({
    date_from: firstDay.toISOString().slice(0, 10),
    date_to: today.toISOString().slice(0, 10),
});

const identification = ref(null);
const mesasAbertas = ref([]);
const loading = reactive({
    identification: false,
    mesas: false,
    arquivo_i: false,
    arquivo_ii: false,
    arquivo_iii: false,
    arquivo_iv: false,
});
const errorMessage = ref('');
const successMessage = ref('');

const fiscalFiles = menuFiscalFiles;
const pafStatus = computed(() => identification.value?.paf_enabled ? 'Ativo' : 'Inativo');
const mesasCount = computed(() => mesasAbertas.value.length);

function formatDateTime(value) {
    if (!value) return '--';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '--';

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function downloadBlob(blob, filename) {
    const url = window.URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    window.URL.revokeObjectURL(url);
}

async function loadIdentification() {
    loading.identification = true;
    errorMessage.value = '';
    try {
        const { data } = await api.get('/menu-fiscal/identificacao');
        identification.value = data || null;
    } catch (requestError) {
        errorMessage.value = await resolveMenuFiscalRequestMessage(requestError, 'Não foi possível carregar a identificação do PAF.');
    } finally {
        loading.identification = false;
    }
}

async function loadMesasAbertas() {
    loading.mesas = true;
    errorMessage.value = '';
    try {
        const { data } = await api.get('/menu-fiscal/mesas-abertas');
        mesasAbertas.value = Array.isArray(data?.items) ? data.items : [];
    } catch (requestError) {
        errorMessage.value = await resolveMenuFiscalRequestMessage(requestError, 'Não foi possível carregar mesas abertas.');
    } finally {
        loading.mesas = false;
    }
}

async function refreshMenuFiscal() {
    await Promise.all([loadIdentification(), loadMesasAbertas()]);
}

async function downloadFiscalFile(file) {
    loading[file.key] = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const { data } = await api.post(file.endpoint, buildMenuFiscalPayload(file, filters), { responseType: 'blob' });
        const blob = data instanceof Blob ? data : new Blob([data], { type: 'application/xml' });
        downloadBlob(blob, file.filename);
        successMessage.value = `${file.title} gerado.`;
    } catch (requestError) {
        errorMessage.value = await resolveMenuFiscalRequestMessage(requestError, 'Não foi possível gerar o XML assinado.');
    } finally {
        loading[file.key] = false;
    }
}

onMounted(refreshMenuFiscal);
</script>

<template>
    <div class="space-y-5">
        <SettingsPageHeader
            title="Menu Fiscal"
            subtitle="PAF-NFC-e Santa Catarina"
        >
            <template #actions>
                <AppButton variant="secondary" :loading="loading.identification || loading.mesas" @click="refreshMenuFiscal">
                    <RefreshCw class="h-4 w-4" aria-hidden="true" />
                    Atualizar
                </AppButton>
            </template>
        </SettingsPageHeader>

        <div v-if="errorMessage" class="menu-fiscal-alert is-error">{{ errorMessage }}</div>
        <div v-if="successMessage" class="menu-fiscal-alert is-success">{{ successMessage }}</div>

        <div class="grid gap-4 lg:grid-cols-[1.2fr_0.8fr]">
            <AppCard class="menu-fiscal-card">
                <div class="menu-fiscal-card__header">
                    <div>
                        <h2>Identificação do PAF-NFC-e</h2>
                        <p>{{ identification?.paf_app_name || 'Freeline PDV' }}</p>
                    </div>
                    <AppBadge :variant="identification?.paf_enabled ? 'success' : 'warning'">{{ pafStatus }}</AppBadge>
                </div>

                <dl class="menu-fiscal-dl">
                    <div>
                        <dt>Versão</dt>
                        <dd>{{ identification?.paf_app_version || '--' }}</dd>
                    </div>
                    <div>
                        <dt>Banco</dt>
                        <dd>{{ identification?.paf_database_architecture || '--' }}</dd>
                    </div>
                    <div>
                        <dt>Arquitetura</dt>
                        <dd>{{ identification?.paf_system_architecture || '--' }}</dd>
                    </div>
                    <div>
                        <dt>Desenvolvedora</dt>
                        <dd>{{ identification?.developer?.razao_social || '--' }}</dd>
                    </div>
                </dl>
            </AppCard>

            <AppCard>
                <div class="menu-fiscal-card__header">
                    <div>
                        <h2>Filtros</h2>
                        <p>Período fiscal</p>
                    </div>
                </div>
                <SettingsFilterBar>
                    <AppInput v-model="filters.date_from" label="De" type="date" />
                    <AppInput v-model="filters.date_to" label="Até" type="date" />
                </SettingsFilterBar>
            </AppCard>
        </div>

        <AppCard>
            <div class="menu-fiscal-card__header">
                <div>
                    <h2>Arquivos fiscais</h2>
                    <p>XML assinado com certificado A1 local</p>
                </div>
                <FileText class="h-5 w-5 text-[var(--color-text-muted)]" aria-hidden="true" />
            </div>

            <div class="menu-fiscal-files">
                <button
                    v-for="file in fiscalFiles"
                    :key="file.key"
                    type="button"
                    class="menu-fiscal-file"
                    :disabled="loading[file.key]"
                    @click="downloadFiscalFile(file)"
                >
                    <span>
                        <strong>{{ file.title }}</strong>
                        <small>{{ file.description }}</small>
                    </span>
                    <Download class="h-4 w-4" aria-hidden="true" />
                </button>
            </div>
        </AppCard>

        <AppCard>
            <div class="menu-fiscal-card__header">
                <div>
                    <h2>Mesas abertas</h2>
                    <p>{{ mesasCount }} registro{{ mesasCount === 1 ? '' : 's' }}</p>
                </div>
                <TableProperties class="h-5 w-5 text-[var(--color-text-muted)]" aria-hidden="true" />
            </div>

            <div class="overflow-x-auto">
                <table class="menu-fiscal-table">
                    <thead>
                        <tr>
                            <th>Mesa</th>
                            <th>Ficha</th>
                            <th>Abertura</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!mesasAbertas.length">
                            <td colspan="4">Sem mesas abertas.</td>
                        </tr>
                        <tr v-for="mesa in mesasAbertas" :key="mesa.id">
                            <td>{{ mesa.table_code || '--' }}</td>
                            <td>{{ mesa.ficha_code || '--' }}</td>
                            <td>{{ formatDateTime(mesa.opened_at) }}</td>
                            <td>{{ Number(mesa.total || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </AppCard>
    </div>
</template>

<style scoped>
.menu-fiscal-alert {
    border-radius: var(--radius-sm);
    padding: 0.85rem 1rem;
    font-size: 0.9rem;
}

.menu-fiscal-alert.is-error {
    background: color-mix(in srgb, var(--color-danger) 12%, var(--color-bg-surface));
    color: var(--color-danger);
}

.menu-fiscal-alert.is-success {
    background: color-mix(in srgb, var(--color-success) 12%, var(--color-bg-surface));
    color: var(--color-success);
}

.menu-fiscal-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
}

.menu-fiscal-card__header h2 {
    margin: 0;
    font-size: 1rem;
    font-weight: 850;
    color: var(--color-text);
}

.menu-fiscal-card__header p {
    margin: 0.2rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.88rem;
}

.menu-fiscal-dl {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
    gap: 0.9rem;
}

.menu-fiscal-dl dt {
    color: var(--color-text-muted);
    font-size: 0.76rem;
    font-weight: 800;
    text-transform: uppercase;
}

.menu-fiscal-dl dd {
    margin: 0.15rem 0 0;
    color: var(--color-text);
    font-weight: 760;
}

.menu-fiscal-files {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
    gap: 0.75rem;
}

.menu-fiscal-file {
    display: flex;
    min-height: 4.5rem;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 55%, transparent);
    border-radius: var(--radius-sm);
    background: var(--color-bg-surface);
    color: var(--color-text);
    padding: 0.9rem;
    text-align: left;
    transition: border-color var(--transition-fast), background var(--transition-fast);
}

.menu-fiscal-file:hover:not(:disabled) {
    border-color: color-mix(in srgb, var(--color-primary) 45%, transparent);
    background: color-mix(in srgb, var(--color-primary) 8%, var(--color-bg-surface));
}

.menu-fiscal-file:disabled {
    cursor: wait;
    opacity: 0.65;
}

.menu-fiscal-file strong,
.menu-fiscal-file small {
    display: block;
}

.menu-fiscal-file small {
    margin-top: 0.15rem;
    color: var(--color-text-muted);
}

.menu-fiscal-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.menu-fiscal-table th,
.menu-fiscal-table td {
    border-bottom: 1px solid color-mix(in srgb, var(--color-border-strong) 35%, transparent);
    padding: 0.75rem;
    text-align: left;
}

.menu-fiscal-table th {
    color: var(--color-text-muted);
    font-size: 0.74rem;
    font-weight: 850;
    text-transform: uppercase;
}
</style>
