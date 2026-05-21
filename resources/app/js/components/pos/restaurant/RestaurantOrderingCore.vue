<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { BellRing, ClipboardList, MonitorPlay } from 'lucide-vue-next';
import { formatCurrency } from '../../../lib/format';
import { useRestaurantOrderingCore } from '../../../composables/useRestaurantOrderingCore';
import AppButton from '../../ui/AppButton.vue';
import AppCard from '../../ui/AppCard.vue';
import AppBadge from '../../ui/AppBadge.vue';
import AppModal from '../../ui/AppModal.vue';
import RestaurantHeader from './RestaurantHeader.vue';
import RestaurantProductSearchBar from './RestaurantProductSearchBar.vue';
import RestaurantCategoryChips from './RestaurantCategoryChips.vue';
import RestaurantProductGrid from './RestaurantProductGrid.vue';
import RestaurantOrderCart from './RestaurantOrderCart.vue';
import RestaurantOrderSummary from './RestaurantOrderSummary.vue';
import RestaurantOrderCartDrawer from './RestaurantOrderCartDrawer.vue';
import RestaurantProductModifierModal from './RestaurantProductModifierModal.vue';
import WaiterActionBar from './WaiterActionBar.vue';
import WaiterContextBar from './WaiterContextBar.vue';
import TableCommandSelectorDrawer from './TableCommandSelectorDrawer.vue';
import CommandSummaryDrawer from './CommandSummaryDrawer.vue';
import ObservationDrawer from './ObservationDrawer.vue';
import StickyOrderFooter from './StickyOrderFooter.vue';

const props = defineProps({
    mode: {
        type: String,
        default: 'auto_atendimento',
    },
    title: {
        type: String,
        default: 'Cardapio digital',
    },
    subtitle: {
        type: String,
        default: '',
    },
    confirmLabel: {
        type: String,
        default: 'Confirmar pedido',
    },
    showCallWaiter: {
        type: Boolean,
        default: false,
    },
    showWaiterActions: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['order-confirmed', 'call-waiter', 'quick-action']);

const ordering = useRestaurantOrderingCore(props.mode);
const viewportWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1440);
const isMobile = computed(() => viewportWidth.value < 960);

const totemStep = ref(props.mode === 'totem' ? 'start' : 'menu');
const totemOrderType = ref('local');
const totemLastOrder = ref(null);
const totemInactivityTimer = ref(null);

const selectorDrawerOpen = ref(false);
const summaryDrawerOpen = ref(false);
const observationDrawerOpen = ref(false);
const conferenceModalOpen = ref(false);
const closeToCashConfirmOpen = ref(false);
const helperModalOpen = ref(false);
const helperModalTitle = ref('Ação');
const helperModalMessage = ref('');

const canShowOrdering = computed(() => props.mode !== 'totem' || totemStep.value === 'menu');
const closeToCashSnapshot = computed(() => {
    const summary = ordering.conferenceSummary.value || ordering.fichaSummary.value || null;
    const mesaCode = String(summary?.mesa?.code || ordering.activeTable.value?.code || '--');
    const fichaCode = String(summary?.ficha?.code || ordering.activeCommand.value?.code || '--');
    const openedAt = summary?.ficha?.openedAt || ordering.activeCommand.value?.openedAt || null;
    const total = Number(summary?.totals?.total ?? ordering.totalFicha.value ?? ordering.activeCommand.value?.total ?? 0);

    return {
        mesaCode,
        fichaCode,
        openedAt,
        total,
    };
});

function updateViewport() {
    viewportWidth.value = window.innerWidth;
}

function clearInactivityTimer() {
    if (!totemInactivityTimer.value) return;
    clearTimeout(totemInactivityTimer.value);
    totemInactivityTimer.value = null;
}

function resetTotemFlow() {
    if (props.mode !== 'totem') return;
    ordering.clearCart();
    ordering.cartDrawerOpen.value = false;
    totemStep.value = 'start';
    totemOrderType.value = 'local';
    totemLastOrder.value = null;
}

function scheduleTotemReset() {
    if (props.mode !== 'totem') return;

    clearInactivityTimer();
    totemInactivityTimer.value = setTimeout(() => {
        if (totemStep.value === 'menu' && ordering.totalItems.value === 0) {
            resetTotemFlow();
        }
    }, 120000);
}

function onTotemActivity() {
    if (props.mode !== 'totem') return;
    if (totemStep.value !== 'menu') return;
    scheduleTotemReset();
}

function startTotem() {
    totemStep.value = 'service-type';
}

function confirmTotemOrderType(value) {
    totemOrderType.value = value;
    totemStep.value = 'menu';
    scheduleTotemReset();
}

function toggleCartDrawer(nextValue = null) {
    ordering.cartDrawerOpen.value = nextValue === null ? !ordering.cartDrawerOpen.value : Boolean(nextValue);
}

function handleProductAdd(product) {
    ordering.openModifierModal(product);
    if (props.mode === 'totem') {
        onTotemActivity();
    }
}

function openHelperModal(title, message) {
    helperModalTitle.value = title;
    helperModalMessage.value = message;
    helperModalOpen.value = true;
}

function formatDateTime(value) {
    if (!value) return '--';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return '--';
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(parsed);
}

function handleConfirmOrder() {
    ordering.confirmOrder()
        .then((confirmation) => {
            if (!confirmation) return;

            emit('order-confirmed', confirmation);
            summaryDrawerOpen.value = true;

            if (props.mode === 'totem') {
                totemLastOrder.value = confirmation;
                totemStep.value = 'confirmation';
                clearInactivityTimer();
            }
        })
        .catch(() => {});
}

function handleCreateFicha(payload = {}, options = {}) {
    ordering.createFichaForSelectedTable(payload, options).catch((requestError) => {
        if (String(requestError?.message || '').includes('pedido atual não enviado')) {
            const confirmDiscard = window.confirm('Existe um pedido atual não enviado. Deseja descartar e seguir com nova ficha?');
            if (!confirmDiscard) return;
            ordering.createFichaForSelectedTable(payload, { ...options, force: true, clearCartOnSuccess: true }).catch(() => {});
        }
    });
}

function handleCallWaiter() {
    emit('call-waiter', {
        table: ordering.activeTable.value,
        command: ordering.activeCommand.value,
    });
}

function handleQuickAction(actionId) {
    emit('quick-action', actionId);

    if (actionId === 'send-kitchen') {
        handleConfirmOrder();
        return;
    }

    if (actionId === 'new-command') {
        handleCreateFicha({
            randomCustomer: true,
            customerName: '',
            fichaCode: '',
        });
        return;
    }

    if (actionId === 'observation') {
        observationDrawerOpen.value = true;
        return;
    }

    if (actionId === 'conference') {
        ordering.loadConferenceSummary()
            .then(() => {
                conferenceModalOpen.value = true;
            })
            .catch(() => {});
        return;
    }

    if (actionId === 'transfer-items') {
        openHelperModal('Transferir itens', 'Transferência de itens ainda não configurada.');
        return;
    }

    if (actionId === 'merge-commands') {
        openHelperModal('Juntar fichas', 'Junção de fichas ainda não configurada.');
    }
}

function handleDrawerConfirmSelection(payload) {
    if (!payload?.tableId) {
        ordering.error.value = 'Selecione uma mesa antes de confirmar.';
        return;
    }

    if (ordering.cart.value.length) {
        const allowSwitch = window.confirm('Existe um pedido atual não enviado. Deseja trocar mesa/ficha sem descartar os itens?');
        if (!allowSwitch) return;
    }

    ordering.selectedTableId.value = payload.tableId;
    ordering.selectedCommandId.value = payload.commandId;
    selectorDrawerOpen.value = false;
}

function openSummaryDrawer() {
    ordering.loadFichaSummary().then(() => {
        summaryDrawerOpen.value = true;
    }).catch(() => {});
}

function handleSaveObservations(payload) {
    ordering.currentOrderObservation.value = String(payload?.orderObservation || '');
    ordering.saveFichaObservation(String(payload?.fichaObservation || ''))
        .then(() => {
            observationDrawerOpen.value = false;
        })
        .catch(() => {});
}

function handleRequestCloseFicha() {
    ordering.requestFichaClosing()
        .then(() => {
            closeToCashConfirmOpen.value = false;
            conferenceModalOpen.value = false;
            summaryDrawerOpen.value = true;
        })
        .catch(() => {});
}

function handleOpenCloseToCashConfirmation() {
    if (!ordering.selectedCommandId.value) {
        ordering.error.value = 'Selecione uma ficha antes de enviar para o caixa.';
        return;
    }

    closeToCashConfirmOpen.value = true;
}

watch(
    () => ordering.successMessage.value,
    (value) => {
        if (!value) return;

        setTimeout(() => {
            ordering.dismissSuccess();
        }, 2600);
    },
);

onMounted(() => {
    window.addEventListener('resize', updateViewport);
    window.addEventListener('mousemove', onTotemActivity);
    window.addEventListener('touchstart', onTotemActivity, { passive: true });
    window.addEventListener('keydown', onTotemActivity);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateViewport);
    window.removeEventListener('mousemove', onTotemActivity);
    window.removeEventListener('touchstart', onTotemActivity);
    window.removeEventListener('keydown', onTotemActivity);
    clearInactivityTimer();
});
</script>

<template>
    <div class="restaurant-ordering-core">
        <AppCard v-if="mode === 'totem' && totemStep === 'start'" class="restaurant-totem-start" elevated>
            <MonitorPlay class="h-12 w-12 text-[var(--color-primary)]" aria-hidden="true" />
            <h2>Toque para comecar</h2>
            <p>Monte seu pedido em poucos passos e retire seu numero ao finalizar.</p>
            <AppButton class="restaurant-totem-start__cta" @click="startTotem">Iniciar pedido</AppButton>
        </AppCard>

        <AppCard v-else-if="mode === 'totem' && totemStep === 'service-type'" class="restaurant-totem-type" elevated>
            <h2>Como voce quer receber seu pedido?</h2>
            <div class="restaurant-totem-type__options">
                <button type="button" class="restaurant-totem-type__option" @click="confirmTotemOrderType('local')">
                    <strong>Comer no local</strong>
                    <small>Receba o pedido na loja</small>
                </button>
                <button type="button" class="restaurant-totem-type__option" @click="confirmTotemOrderType('balcao')">
                    <strong>Retirar no balcao</strong>
                    <small>Pedido para viagem</small>
                </button>
                <button type="button" class="restaurant-totem-type__option" @click="confirmTotemOrderType('mesa')">
                    <strong>Mesa</strong>
                    <small>Informe mesa e ficha</small>
                </button>
            </div>
        </AppCard>

        <AppCard v-else-if="mode === 'totem' && totemStep === 'confirmation'" class="restaurant-totem-confirm" elevated>
            <h2>Pedido confirmado</h2>
            <p class="restaurant-totem-confirm__code">Senha <strong>{{ totemLastOrder?.id || '--' }}</strong></p>
            <p>Acompanhe o painel para retirada. Tempo medio estimado: 15 minutos.</p>
            <div class="restaurant-totem-confirm__actions">
                <AppButton variant="secondary" @click="resetTotemFlow">Novo pedido</AppButton>
                <RouterLink to="/">
                    <AppButton>Voltar ao PDV</AppButton>
                </RouterLink>
            </div>
        </AppCard>

        <template v-if="canShowOrdering">
            <AppCard v-if="ordering.loading.value" class="restaurant-ordering-core__loading">
                Carregando mesas, fichas e cardapio...
            </AppCard>

            <RestaurantHeader
                v-else
                :title="title"
                :subtitle="subtitle"
                :table-label="ordering.activeTable.value?.code || '—'"
                :command-label="ordering.activeCommand.value?.code || '—'"
                :waiter-name="ordering.waiter.value?.name || ordering.activeCommand.value?.waiterName || 'Equipe'"
                :status-label="ordering.statusFichaLabel.value"
            >
                <template #actions>
                    <AppBadge v-if="mode === 'totem'" variant="warning">Totem</AppBadge>
                    <AppButton v-if="showCallWaiter" variant="secondary" @click="handleCallWaiter">
                        <BellRing class="h-4 w-4" aria-hidden="true" />
                        Chamar garcom
                    </AppButton>
                    <AppButton v-if="isMobile" variant="secondary" @click="toggleCartDrawer()">
                        <ClipboardList class="h-4 w-4" aria-hidden="true" />
                        Pedido atual
                    </AppButton>
                </template>
            </RestaurantHeader>

            <WaiterContextBar
                v-if="!ordering.loading.value"
                :waiter-name="ordering.waiter.value?.name || 'Equipe'"
                :table-label="ordering.activeTable.value?.code || '--'"
                :command-label="ordering.activeCommand.value?.code || '--'"
                :status-label="ordering.statusFichaLabel.value"
                :status-variant="ordering.statusFichaVariant.value"
                :total-label="formatCurrency(ordering.totalFicha.value || 0)"
                @switch="selectorDrawerOpen = true"
            />

            <template v-if="!ordering.loading.value">
                <WaiterActionBar v-if="showWaiterActions" @action="handleQuickAction" />

                <p v-if="ordering.successMessage.value" class="restaurant-ordering-core__feedback text-success">
                    {{ ordering.successMessage.value }}
                </p>
                <p v-if="ordering.error.value" class="restaurant-ordering-core__feedback text-danger">
                    {{ ordering.error.value }}
                </p>

                <AppCard v-if="!ordering.canAddItemsToFicha.value" class="restaurant-ordering-core__warning">
                    Esta ficha está aguardando pagamento. Reabra a ficha para adicionar novos itens.
                </AppCard>

                <div class="restaurant-ordering-core__filters">
                    <RestaurantProductSearchBar
                        :model-value="ordering.searchQuery.value"
                        :placeholder="mode === 'totem' ? 'Buscar no cardapio do totem' : 'Buscar item no cardapio'"
                        @update:model-value="ordering.searchQuery.value = $event"
                    />
                    <RestaurantCategoryChips
                        :categories="ordering.categories.value"
                        :active-category="ordering.activeCategory.value"
                        @update:active-category="ordering.activeCategory.value = $event"
                    />
                </div>

                <div class="restaurant-ordering-core__content" :class="{ 'is-totem': mode === 'totem' }">
                    <section class="restaurant-ordering-core__catalog">
                        <RestaurantProductGrid
                            :products="ordering.filteredProducts.value"
                            :compact="showWaiterActions"
                            :format-currency="formatCurrency"
                            @add="handleProductAdd"
                        />
                    </section>

                    <aside v-if="!isMobile" class="restaurant-ordering-core__cart">
                        <RestaurantOrderCart
                            :items="ordering.cart.value"
                            :format-currency="formatCurrency"
                            :title="mode === 'totem' ? 'Carrinho do pedido' : 'Pedido atual'"
                            @increase="ordering.increaseItem"
                            @decrease="ordering.decreaseItem"
                            @remove="ordering.removeItem"
                        />
                        <RestaurantOrderSummary
                            :format-currency="formatCurrency"
                            :subtotal="ordering.subtotal.value"
                            :total-items="ordering.totalItems.value"
                            :confirm-label="confirmLabel"
                            :disabled="!ordering.cart.value.length || ordering.confirmingOrder.value"
                            @confirm="handleConfirmOrder"
                        />

                        <AppButton variant="secondary" @click="openSummaryDrawer">Ver ficha</AppButton>
                    </aside>
                </div>
            </template>

            <StickyOrderFooter
                v-if="isMobile && !ordering.loading.value"
                :has-selection="Boolean(ordering.selectedTableId.value && ordering.selectedCommandId.value)"
                :has-items="ordering.cart.value.length > 0"
                :items-count="ordering.totalItems.value"
                :subtotal-label="formatCurrency(ordering.totalPedidoAtual.value)"
                :command-label="ordering.activeCommand.value?.code || '--'"
                :total-ficha-label="formatCurrency(ordering.totalFicha.value || 0)"
                :sending="ordering.confirmingOrder.value"
                @open-order="toggleCartDrawer(true)"
                @open-summary="openSummaryDrawer"
                @send="handleConfirmOrder"
                @switch="selectorDrawerOpen = true"
            />

            <RestaurantOrderCartDrawer
                :open="ordering.cartDrawerOpen.value && !ordering.loading.value"
                :items="ordering.cart.value"
                :subtotal="ordering.subtotal.value"
                :total-items="ordering.totalItems.value"
                :confirm-label="confirmLabel"
                title="Pedido atual"
                :format-currency="formatCurrency"
                @close="toggleCartDrawer(false)"
                @increase="ordering.increaseItem"
                @decrease="ordering.decreaseItem"
                @remove="ordering.removeItem"
                @confirm="handleConfirmOrder"
            />

            <TableCommandSelectorDrawer
                :open="selectorDrawerOpen"
                :tables="ordering.tables.value"
                :selected-table-id="ordering.selectedTableId.value"
                :selected-command-id="ordering.selectedCommandId.value"
                :creating-ficha="ordering.creatingFicha.value"
                :use-modal="!isMobile"
                :format-currency="formatCurrency"
                @close="selectorDrawerOpen = false"
                @confirm="handleDrawerConfirmSelection"
                @create-ficha="handleCreateFicha({ randomCustomer: true, customerName: '', fichaCode: '' })"
            />

            <CommandSummaryDrawer
                :open="summaryDrawerOpen"
                :summary="ordering.fichaSummary.value"
                :loading="ordering.loadingSummary.value"
                :requesting-close="ordering.requestingClose.value"
                :format-currency="formatCurrency"
                :status-label="ordering.statusFichaLabel.value"
                :status-variant="ordering.statusFichaVariant.value"
                @close="summaryDrawerOpen = false"
                @refresh="ordering.loadFichaSummary().catch(() => {})"
                @conference="handleQuickAction('conference')"
                @close-request="handleOpenCloseToCashConfirmation"
                @add-more="summaryDrawerOpen = false"
            />

            <ObservationDrawer
                :open="observationDrawerOpen"
                :ficha-observation="ordering.fichaSummary.value?.ficha?.observation || ''"
                :order-observation="ordering.currentOrderObservation.value"
                :saving="ordering.savingObservation.value"
                @close="observationDrawerOpen = false"
                @save="handleSaveObservations"
            />

            <AppModal :open="conferenceModalOpen" title="Conferência" @close="conferenceModalOpen = false">
                <div class="restaurant-ordering-core__conference">
                    <p><strong>Mesa:</strong> {{ ordering.conferenceSummary.value?.mesa?.code || '--' }}</p>
                    <p><strong>Ficha:</strong> {{ ordering.conferenceSummary.value?.ficha?.code || '--' }}</p>
                    <p><strong>Garçom:</strong> {{ ordering.conferenceSummary.value?.ficha?.waiterName || 'Equipe' }}</p>
                    <p><strong>Status:</strong> {{ ordering.statusFichaLabel.value }}</p>
                    <p><strong>Total:</strong> {{ formatCurrency(ordering.conferenceSummary.value?.totals?.total || 0) }}</p>
                    <AppButton variant="secondary" @click="openHelperModal('Conferência', 'Impressão de conferência ainda não configurada.')">
                        Imprimir conferência
                    </AppButton>
                    <AppButton @click="handleOpenCloseToCashConfirmation">Solicitar fechamento</AppButton>
                </div>
            </AppModal>

            <AppModal :open="closeToCashConfirmOpen" title="Enviar ficha para o caixa" @close="closeToCashConfirmOpen = false">
                <div class="restaurant-ordering-core__close-confirm">
                    <p>Você tem certeza que deseja enviar esta ficha para o caixa?</p>
                    <p><strong>Mesa:</strong> {{ closeToCashSnapshot.mesaCode }}</p>
                    <p><strong>Nome da ficha:</strong> {{ closeToCashSnapshot.fichaCode }}</p>
                    <p><strong>Data/hora de abertura:</strong> {{ formatDateTime(closeToCashSnapshot.openedAt) }}</p>
                    <p><strong>Valor da nota:</strong> {{ formatCurrency(closeToCashSnapshot.total) }}</p>
                    <div class="restaurant-ordering-core__close-confirm-actions">
                        <AppButton variant="secondary" @click="closeToCashConfirmOpen = false">Cancelar</AppButton>
                        <AppButton :loading="ordering.requestingClose.value" @click="handleRequestCloseFicha">
                            Sim, enviar para o caixa
                        </AppButton>
                    </div>
                </div>
            </AppModal>

            <AppModal :open="helperModalOpen" :title="helperModalTitle" @close="helperModalOpen = false">
                <p class="restaurant-ordering-core__helper-text">{{ helperModalMessage }}</p>
            </AppModal>

            <RestaurantProductModifierModal
                :open="ordering.modifierModal.open"
                :product="ordering.modifierModal.product"
                :modifiers="ordering.getProductModifiers(ordering.modifierModal.product?.id)"
                :quantity="ordering.modifierModal.quantity"
                :observation="ordering.modifierModal.observation"
                :selected-options="ordering.modifierModal.selectedOptions"
                :removed-ingredients="ordering.modifierModal.removedIngredients"
                :classification-parameters="ordering.modifierModal.classificationParameters"
                :classification-parameter-values="ordering.modifierModal.classificationParameterValues"
                :format-currency="formatCurrency"
                @close="ordering.closeModifierModal"
                @submit="ordering.submitModifierProduct"
                @update:quantity="ordering.modifierModal.quantity = $event"
                @update:observation="ordering.modifierModal.observation = $event"
                @update:selected-options="ordering.modifierModal.selectedOptions = $event"
                @update:removed-ingredients="ordering.modifierModal.removedIngredients = $event"
                @update:classification-parameter-values="ordering.modifierModal.classificationParameterValues = $event"
            />
        </template>
    </div>
</template>

<style scoped>
.restaurant-ordering-core {
    display: grid;
    gap: 0.62rem;
    padding-bottom: 5.3rem;
}

.restaurant-ordering-core__feedback {
    margin: 0;
    border: 1px solid color-mix(in srgb, var(--color-success) 45%, transparent);
    border-radius: 0.7rem;
    background: color-mix(in srgb, var(--color-success) 10%, var(--color-bg-surface));
    padding: 0.52rem 0.66rem;
    font-size: 0.86rem;
    font-weight: 700;
}

.restaurant-ordering-core__warning {
    padding: 0.6rem;
    border-color: color-mix(in srgb, var(--color-warning) 50%, transparent);
    color: color-mix(in srgb, var(--color-warning) 82%, white);
    font-size: 0.84rem;
    font-weight: 700;
}

.restaurant-ordering-core__conference {
    display: grid;
    gap: 0.42rem;
}

.restaurant-ordering-core__conference p,
.restaurant-ordering-core__helper-text {
    margin: 0;
    font-size: 0.86rem;
    color: var(--color-text-muted);
}

.restaurant-ordering-core__close-confirm {
    display: grid;
    gap: 0.5rem;
}

.restaurant-ordering-core__close-confirm p {
    margin: 0;
    font-size: 0.9rem;
    color: var(--color-text-muted);
}

.restaurant-ordering-core__close-confirm-actions {
    margin-top: 0.2rem;
    display: grid;
    gap: 0.45rem;
    grid-template-columns: 1fr 1fr;
}

.restaurant-ordering-core__loading {
    padding: 1rem;
    text-align: center;
    color: var(--color-text-muted);
    font-weight: 700;
}

.restaurant-ordering-core__filters {
    display: grid;
    gap: 0.48rem;
}

.restaurant-ordering-core__content {
    display: grid;
    gap: 0.65rem;
    grid-template-columns: minmax(0, 1fr) 22rem;
    align-items: start;
}

.restaurant-ordering-core__content.is-totem {
    grid-template-columns: minmax(0, 1fr) 25rem;
}

.restaurant-ordering-core__catalog {
    min-width: 0;
}

.restaurant-ordering-core__cart {
    position: sticky;
    top: 0.55rem;
    display: grid;
    gap: 0.55rem;
}

.restaurant-totem-start,
.restaurant-totem-type,
.restaurant-totem-confirm {
    min-height: 66dvh;
    display: grid;
    place-content: center;
    justify-items: center;
    gap: 0.75rem;
    text-align: center;
    padding: 1.2rem;
}

.restaurant-totem-start h2,
.restaurant-totem-type h2,
.restaurant-totem-confirm h2 {
    margin: 0;
    color: var(--color-text);
    font-size: clamp(1.22rem, 3vw, 1.8rem);
}

.restaurant-totem-start p,
.restaurant-totem-type p,
.restaurant-totem-confirm p {
    margin: 0;
    color: var(--color-text-muted);
}

.restaurant-totem-start__cta {
    min-width: 13rem;
    min-height: 3rem;
    font-size: 1.08rem;
}

.restaurant-totem-type__options {
    width: min(58rem, 100%);
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.7rem;
}

.restaurant-totem-type__option {
    border: 1px solid var(--color-border-strong);
    border-radius: 1rem;
    background: var(--color-bg-surface);
    padding: 1rem;
    display: grid;
    gap: 0.38rem;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.restaurant-totem-type__option strong {
    color: var(--color-text);
    font-size: 1rem;
}

.restaurant-totem-type__option small {
    color: var(--color-text-muted);
    font-size: 0.82rem;
}

.restaurant-totem-type__option:hover {
    border-color: color-mix(in srgb, var(--color-primary) 56%, transparent);
    background: color-mix(in srgb, var(--color-primary) 12%, var(--color-bg-surface));
}

.restaurant-totem-confirm__code {
    font-size: 1.42rem;
    color: var(--color-primary);
    font-weight: 800;
}

.restaurant-totem-confirm__actions {
    display: flex;
    gap: 0.6rem;
    flex-wrap: wrap;
}

@media (min-width: 960px) {
    .restaurant-ordering-core {
        padding-bottom: 1rem;
    }
}

@media (max-width: 959px) {
    .restaurant-ordering-core__content,
    .restaurant-ordering-core__content.is-totem {
        grid-template-columns: 1fr;
    }

    .restaurant-totem-type__options {
        grid-template-columns: 1fr;
    }
}
</style>
