<script setup>
import { computed, ref } from 'vue';
import AppButton from '../../ui/AppButton.vue';
import AppCard from '../../ui/AppCard.vue';
import AppInput from '../../ui/AppInput.vue';
import AppSelect from '../../ui/AppSelect.vue';
import AppSwitch from '../../ui/AppSwitch.vue';
import { buildAutomaticTicketCodePreview, buildManualTicketPreview } from '../../../lib/restaurantParameters';

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
    variant: {
        type: String,
        default: 'automatic',
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['update:modelValue']);
const showPreview = ref(true);

const automaticCodePreview = computed(() => buildAutomaticTicketCodePreview(props.modelValue));
const manualPreview = computed(() => buildManualTicketPreview(props.modelValue));

const requireTableDisabled = computed(() => Boolean(props.modelValue.allow_without_table));

function updateField(field, value) {
    if (field === 'allow_without_table' && value) {
        emit('update:modelValue', {
            ...props.modelValue,
            allow_without_table: true,
            require_table: false,
        });
        return;
    }

    if (field === 'require_table' && value) {
        emit('update:modelValue', {
            ...props.modelValue,
            require_table: true,
            allow_without_table: false,
        });
        return;
    }

    emit('update:modelValue', {
        ...props.modelValue,
        [field]: value,
    });
}

function updateNumericField(field, rawValue) {
    const parsed = Number.parseInt(String(rawValue ?? ''), 10);
    updateField(field, Number.isNaN(parsed) ? 0 : parsed);
}
</script>

<template>
    <AppCard class="restaurant-config-card">
        <div class="restaurant-config-card__head">
            <h3>{{ variant === 'manual' ? 'Fichas/Comandas fixas' : 'Comandas/Fichas automáticas' }}</h3>
            <p v-if="variant === 'manual'">Cadastre fichas finitas e controle a reutilização após fechamento.</p>
            <p v-else>Configure a abertura de comandas sob demanda durante o atendimento.</p>
        </div>

        <div v-if="variant === 'automatic'" class="restaurant-switch-grid">
            <div class="restaurant-switch-row">
                <AppSwitch :model-value="modelValue.allow_without_table" @update:model-value="updateField('allow_without_table', $event)" />
                <div>
                    <p class="restaurant-switch-row__label">Permitir ficha/comanda sem mesa</p>
                    <p class="restaurant-switch-row__hint">Permite abrir uma comanda avulsa, sem vincular a uma mesa.</p>
                </div>
            </div>

            <div class="restaurant-switch-row" :class="{ 'is-disabled': requireTableDisabled }">
                <AppSwitch :model-value="modelValue.require_table" @update:model-value="updateField('require_table', $event)" />
                <div>
                    <p class="restaurant-switch-row__label">Mesa obrigatória para abrir pedido</p>
                    <p class="restaurant-switch-row__hint">Se a comanda sem mesa estiver ativa, essa opção é desativada.</p>
                </div>
            </div>

            <div class="restaurant-switch-row">
                <AppSwitch :model-value="modelValue.allow_multiple_per_table" @update:model-value="updateField('allow_multiple_per_table', $event)" />
                <div>
                    <p class="restaurant-switch-row__label">Permitir várias fichas/comandas na mesma mesa</p>
                    <p class="restaurant-switch-row__hint">Útil quando clientes da mesma mesa desejam pagar separadamente.</p>
                </div>
            </div>
        </div>

        <div v-if="variant === 'automatic'" class="restaurant-config-grid">
            <AppSelect
                :model-value="modelValue.code_generation_type"
                label="Tipo de geração do código"
                @update:model-value="updateField('code_generation_type', $event)"
            >
                <option value="continuous">Sequencial contínuo</option>
                <option value="daily">Sequencial diário</option>
                <option value="random">Aleatório curto</option>
            </AppSelect>

            <AppInput
                :model-value="modelValue.prefix"
                label="Prefixo da ficha/comanda"
                maxlength="30"
                :error="errors['tabs_or_tickets.prefix']"
                @update:model-value="updateField('prefix', $event)"
            />

            <AppInput
                v-if="modelValue.code_generation_type !== 'random'"
                :model-value="modelValue.start_number"
                label="Próximo número inicial"
                type="number"
                min="0"
                @update:model-value="updateNumericField('start_number', $event)"
            />
            <AppInput
                v-if="modelValue.code_generation_type !== 'random'"
                :model-value="modelValue.padding"
                label="Casas com zeros à esquerda"
                type="number"
                min="1"
                max="6"
                :error="errors['tabs_or_tickets.padding']"
                @update:model-value="updateNumericField('padding', $event)"
            />

            <AppInput
                v-if="modelValue.code_generation_type === 'random'"
                :model-value="modelValue.random_code_length"
                label="Tamanho do código aleatório"
                type="number"
                min="3"
                max="10"
                :error="errors['tabs_or_tickets.random_code_length']"
                @update:model-value="updateNumericField('random_code_length', $event)"
            />
        </div>

        <div v-if="variant === 'automatic'" class="restaurant-preview-chip">
            <p><strong>Preview do próximo código:</strong> {{ automaticCodePreview }}</p>
        </div>

        <div v-if="variant === 'manual'" class="restaurant-config-grid">
            <AppInput
                :model-value="modelValue.quantity"
                label="Quantidade de fichas/comandas"
                type="number"
                min="0"
                :error="errors['tabs_or_tickets.quantity']"
                @update:model-value="updateNumericField('quantity', $event)"
            />
            <AppInput
                :model-value="modelValue.prefix"
                label="Prefixo das fichas"
                maxlength="30"
                :error="errors['tabs_or_tickets.prefix']"
                @update:model-value="updateField('prefix', $event)"
            />
            <AppInput
                :model-value="modelValue.start_number"
                label="Número inicial das fichas"
                type="number"
                min="0"
                @update:model-value="updateNumericField('start_number', $event)"
            />
            <AppInput
                :model-value="modelValue.padding"
                label="Zeros à esquerda"
                type="number"
                min="1"
                max="6"
                :error="errors['tabs_or_tickets.padding']"
                @update:model-value="updateNumericField('padding', $event)"
            />
        </div>

        <div v-if="variant === 'manual'" class="restaurant-switch-grid">
            <div class="restaurant-switch-row">
                <AppSwitch :model-value="modelValue.reuse_after_closing" @update:model-value="updateField('reuse_after_closing', $event)" />
                <div>
                    <p class="restaurant-switch-row__label">Reutilizar ficha após fechamento da conta</p>
                    <p class="restaurant-switch-row__hint">Após fechar a conta, a ficha volta a ficar disponível para outro atendimento.</p>
                </div>
            </div>

            <div class="restaurant-switch-row">
                <AppSwitch :model-value="modelValue.allow_blocking" @update:model-value="updateField('allow_blocking', $event)" />
                <div>
                    <p class="restaurant-switch-row__label">Permitir bloquear fichas</p>
                    <p class="restaurant-switch-row__hint">Permite marcar uma ficha como indisponível temporariamente.</p>
                </div>
            </div>
        </div>

        <div v-if="variant === 'manual'" class="restaurant-preview-area">
            <AppButton variant="secondary" @click="showPreview = !showPreview">
                {{ showPreview ? 'Ocultar pré-visualização de fichas' : 'Pré-visualizar fichas' }}
            </AppButton>
            <p v-if="showPreview" class="restaurant-preview-text">{{ manualPreview.join(', ') }}...</p>
        </div>
    </AppCard>
</template>

<style scoped>
.restaurant-config-card {
    display: grid;
    gap: 1rem;
}

.restaurant-config-card__head h3 {
    margin: 0;
    font-size: 1.03rem;
    font-weight: 800;
    color: var(--color-text);
}

.restaurant-config-card__head p {
    margin: 0.32rem 0 0;
    color: var(--color-text-muted);
    font-size: 0.85rem;
}

.restaurant-config-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
    gap: 0.75rem;
}

.restaurant-switch-grid {
    display: grid;
    gap: 0.75rem;
}

.restaurant-switch-row {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 0.7rem;
    align-items: start;
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 38%, transparent);
    border-radius: var(--radius-sm);
    padding: 0.65rem 0.7rem;
    background: color-mix(in srgb, var(--color-bg-surface) 88%, transparent);
}

.restaurant-switch-row.is-disabled {
    opacity: 0.6;
    pointer-events: none;
}

.restaurant-switch-row__label {
    margin: 0;
    font-size: 0.84rem;
    color: var(--color-text);
    font-weight: 700;
}

.restaurant-switch-row__hint {
    margin: 0.2rem 0 0;
    font-size: 0.78rem;
    color: var(--color-text-muted);
}

.restaurant-preview-chip {
    border-radius: var(--radius-sm);
    border: 1px solid color-mix(in srgb, var(--color-primary) 32%, transparent);
    background: color-mix(in srgb, var(--color-primary) 12%, var(--color-bg-surface));
    padding: 0.65rem 0.75rem;
}

.restaurant-preview-chip p {
    margin: 0;
    font-size: 0.84rem;
    color: var(--color-text);
}

.restaurant-preview-area {
    display: grid;
    gap: 0.5rem;
}

.restaurant-preview-text {
    margin: 0;
    font-size: 0.83rem;
    color: var(--color-text-muted);
}
</style>
