<script setup>
import { computed, ref } from 'vue';
import AppButton from '../../ui/AppButton.vue';
import AppCard from '../../ui/AppCard.vue';
import AppInput from '../../ui/AppInput.vue';
import AppSwitch from '../../ui/AppSwitch.vue';
import { buildTablePreview } from '../../../lib/restaurantParameters';

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

const showPreview = ref(true);

const previewList = computed(() => buildTablePreview(props.modelValue));

function updateField(field, value) {
    emit('update:modelValue', {
        ...props.modelValue,
        [field]: value,
    });
}

function updateNumericField(field, rawValue) {
    const parsed = Number.parseInt(String(rawValue ?? ''), 10);
    updateField(field, Number.isNaN(parsed) ? 0 : parsed);
}

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <AppCard class="restaurant-config-card">
        <div class="restaurant-config-card__head">
            <h3>{{ variant === 'manual' ? 'Mesas fixas' : 'Mesas automáticas' }}</h3>
            <p v-if="variant === 'manual'">Defina o cadastro e o comportamento das mesas do salão.</p>
            <p v-else>Controle a criação de mesas conforme a operação em andamento.</p>
        </div>

        <div v-if="variant === 'manual'" class="restaurant-config-grid">
            <AppInput
                :model-value="modelValue.quantity"
                label="Quantidade de mesas"
                type="number"
                min="0"
                :error="errors['tables.quantity']"
                @update:model-value="updateNumericField('quantity', $event)"
            />
            <AppInput
                :model-value="modelValue.prefix"
                label="Prefixo das mesas"
                maxlength="30"
                :error="errors['tables.prefix']"
                @update:model-value="updateField('prefix', $event)"
            />
            <AppInput
                :model-value="modelValue.start_number"
                label="Número inicial das mesas"
                type="number"
                min="0"
                @update:model-value="updateNumericField('start_number', $event)"
            />
            <AppInput
                :model-value="modelValue.padding"
                label="Casas com zeros à esquerda"
                type="number"
                min="1"
                max="6"
                :error="errors['tables.padding']"
                hint="Ex.: 2 gera Mesa 01, 02..."
                @update:model-value="updateNumericField('padding', $event)"
            />
        </div>

        <div v-if="variant === 'manual'" class="restaurant-switch-grid">
            <div class="restaurant-switch-row">
                <AppSwitch :model-value="modelValue.allow_manual_rename" @update:model-value="updateField('allow_manual_rename', $event)" />
                <div>
                    <p class="restaurant-switch-row__label">Permitir renomear mesas manualmente</p>
                    <p class="restaurant-switch-row__hint">Permite editar o nome da mesa além do padrão numérico.</p>
                </div>
            </div>

            <div class="restaurant-switch-row">
                <AppSwitch :model-value="modelValue.allow_blocking" @update:model-value="updateField('allow_blocking', $event)" />
                <div>
                    <p class="restaurant-switch-row__label">Permitir bloquear mesas</p>
                    <p class="restaurant-switch-row__hint">Permite marcar uma mesa como indisponível temporariamente.</p>
                </div>
            </div>

            <div class="restaurant-switch-row">
                <AppSwitch :model-value="modelValue.use_capacity" @update:model-value="updateField('use_capacity', $event)" />
                <div>
                    <p class="restaurant-switch-row__label">Usar capacidade por mesa</p>
                    <p class="restaurant-switch-row__hint">Ativa controle de quantidade de pessoas por mesa.</p>
                </div>
            </div>

            <AppInput
                v-if="modelValue.use_capacity"
                :model-value="modelValue.default_capacity"
                label="Capacidade padrão"
                type="number"
                min="1"
                @update:model-value="updateNumericField('default_capacity', $event)"
            />
        </div>

        <div v-if="variant === 'automatic'" class="restaurant-switch-grid">
            <div class="restaurant-switch-row">
                <AppSwitch
                    :model-value="modelValue.allow_create_during_service"
                    @update:model-value="updateField('allow_create_during_service', $event)"
                />
                <div>
                    <p class="restaurant-switch-row__label">Permitir criar mesa durante o atendimento</p>
                    <p class="restaurant-switch-row__hint">Cria mesas sob demanda sem cadastro prévio.</p>
                </div>
            </div>

            <AppInput
                :model-value="modelValue.prefix"
                label="Prefixo padrão da mesa"
                maxlength="30"
                :error="errors['tables.prefix']"
                @update:model-value="updateField('prefix', $event)"
            />

            <div class="restaurant-switch-row">
                <AppSwitch :model-value="modelValue.allow_temporary_table" @update:model-value="updateField('allow_temporary_table', $event)" />
                <div>
                    <p class="restaurant-switch-row__label">Permitir mesa temporária</p>
                    <p class="restaurant-switch-row__hint">Ex.: Balcão, Retirada, Delivery e Avulsa.</p>
                </div>
            </div>
        </div>

        <div v-if="variant === 'manual'" class="restaurant-preview-area">
            <AppButton variant="secondary" @click="showPreview = !showPreview">
                {{ showPreview ? 'Ocultar pré-visualização de mesas' : 'Pré-visualizar mesas' }}
            </AppButton>
            <p v-if="showPreview" class="restaurant-preview-text">{{ previewList.join(', ') }}...</p>
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
