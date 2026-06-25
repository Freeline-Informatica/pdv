<script setup>
import { computed } from 'vue';
import AppModal from '../../ui/AppModal.vue';
import AppButton from '../../ui/AppButton.vue';
import AppTextarea from '../../ui/AppTextarea.vue';
import AppInput from '../../ui/AppInput.vue';
import AppSelect from '../../ui/AppSelect.vue';
import AppCheckbox from '../../ui/AppCheckbox.vue';
import RestaurantQuantityStepper from './RestaurantQuantityStepper.vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    product: {
        type: Object,
        default: null,
    },
    modifiers: {
        type: Object,
        default: () => ({ adicionais: [], removerIngredientes: [] }),
    },
    quantity: {
        type: Number,
        default: 1,
    },
    observation: {
        type: String,
        default: '',
    },
    selectedOptions: {
        type: Array,
        default: () => [],
    },
    removedIngredients: {
        type: Array,
        default: () => [],
    },
    classificationParameters: {
        type: Array,
        default: () => [],
    },
    classificationParameterValues: {
        type: Object,
        default: () => ({}),
    },
    formatCurrency: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits([
    'close',
    'submit',
    'update:quantity',
    'update:observation',
    'update:selectedOptions',
    'update:removedIngredients',
    'update:classificationParameterValues',
]);

const groupByOptionId = computed(() => {
    const map = {};
    props.modifiers.adicionais.forEach((group) => {
        group.opcoes.forEach((option) => {
            map[option.id] = group;
        });
    });
    return map;
});

function optionChecked(optionId) {
    return props.selectedOptions.includes(optionId);
}

function toggleOption(optionId) {
    const selected = [...props.selectedOptions];
    const index = selected.indexOf(optionId);

    if (index >= 0) {
        selected.splice(index, 1);
        emit('update:selectedOptions', selected);
        return;
    }

    const group = groupByOptionId.value[optionId];
    if (group?.max) {
        const selectedInGroup = selected.filter((id) => groupByOptionId.value[id]?.id === group.id);
        if (selectedInGroup.length >= group.max) {
            return;
        }
    }

    selected.push(optionId);
    emit('update:selectedOptions', selected);
}

function ingredientRemoved(name) {
    return props.removedIngredients.includes(name);
}

function toggleIngredient(name) {
    const next = [...props.removedIngredients];
    const index = next.indexOf(name);

    if (index >= 0) {
        next.splice(index, 1);
    } else {
        next.push(name);
    }

    emit('update:removedIngredients', next);
}

const observationFieldNameTemplates = [
    { id: 'personalizado', label: 'Personalizado' },
    { id: 'observacao_padrao', label: 'Observação padrão' },
    { id: 'sem_item', label: 'Sem item' },
    { id: 'com_item', label: 'Com item' },
    { id: 'intensidade', label: 'Intensidade' },
    { id: 'temperatura', label: 'Temperatura' },
];

function parameterLabel(field) {
    const templateId = String(field?.nome_template || 'personalizado');
    if (templateId === 'personalizado') {
        return String(field?.nome_personalizado || '').trim() || 'Campo adicional';
    }
    return observationFieldNameTemplates.find((option) => option.id === templateId)?.label || 'Campo adicional';
}

function parameterValue(fieldId) {
    return props.classificationParameterValues?.[fieldId];
}

function updateParameter(fieldId, value) {
    emit('update:classificationParameterValues', {
        ...(props.classificationParameterValues || {}),
        [fieldId]: value,
    });
}
</script>

<template>
    <AppModal :open="open" :title="product ? `Personalizar ${product.nome}` : 'Personalizar item'" width-class="max-w-2xl" @close="emit('close')">
        <div class="modifier-modal">
            <section class="modifier-modal__line">
                <div>
                    <p class="modifier-modal__label">Quantidade</p>
                    <RestaurantQuantityStepper :model-value="quantity" @update:model-value="emit('update:quantity', $event)" />
                </div>
                <div class="modifier-modal__price" v-if="product">
                    <span>Valor base</span>
                    <strong>{{ formatCurrency(product.preco) }}</strong>
                </div>
            </section>

            <section v-for="group in modifiers.adicionais" :key="group.id" class="modifier-modal__section">
                <h4>{{ group.nome }}</h4>
                <small class="text-muted">Escolha até {{ group.max || 'N' }} opção(ões)</small>
                <div class="modifier-modal__checks">
                    <label v-for="option in group.opcoes" :key="option.id" class="modifier-modal__check">
                        <input
                            type="checkbox"
                            :checked="optionChecked(option.id)"
                            @change="toggleOption(option.id)"
                        >
                        <span>{{ option.nome }}</span>
                        <small>{{ formatCurrency(option.preco) }}</small>
                    </label>
                </div>
            </section>

            <section v-if="modifiers.removerIngredientes?.length" class="modifier-modal__section">
                <h4>Remover ingredientes</h4>
                <div class="modifier-modal__checks">
                    <label v-for="ingredient in modifiers.removerIngredientes" :key="ingredient" class="modifier-modal__check">
                        <input
                            type="checkbox"
                            :checked="ingredientRemoved(ingredient)"
                            @change="toggleIngredient(ingredient)"
                        >
                        <span>Sem {{ ingredient }}</span>
                    </label>
                </div>
            </section>

            <section v-if="classificationParameters.length" class="modifier-modal__section">
                <h4>Parâmetros da classificação</h4>
                <small class="text-muted">Configurações desta classificação mercadológica para o item.</small>

                <div class="modifier-modal__classification-fields">
                    <template v-for="field in classificationParameters" :key="field.id">
                        <AppInput
                            v-if="field.tipo_campo === 'texto_curto'"
                            :model-value="String(parameterValue(field.id) ?? '')"
                            :label="parameterLabel(field)"
                            :placeholder="`Informe ${parameterLabel(field).toLowerCase()}`"
                            @update:model-value="updateParameter(field.id, $event)"
                        />
                        <AppTextarea
                            v-else-if="field.tipo_campo === 'texto_longo'"
                            :model-value="String(parameterValue(field.id) ?? '')"
                            :label="parameterLabel(field)"
                            rows="2"
                            :placeholder="`Informe ${parameterLabel(field).toLowerCase()}`"
                            @update:model-value="updateParameter(field.id, $event)"
                        />
                        <AppInput
                            v-else-if="field.tipo_campo === 'numero_inteiro'"
                            :model-value="String(parameterValue(field.id) ?? '')"
                            :label="parameterLabel(field)"
                            type="number"
                            step="1"
                            inputmode="numeric"
                            @update:model-value="updateParameter(field.id, $event)"
                        />
                        <AppInput
                            v-else-if="field.tipo_campo === 'numero_decimal'"
                            :model-value="String(parameterValue(field.id) ?? '')"
                            :label="parameterLabel(field)"
                            type="number"
                            step="0.01"
                            inputmode="decimal"
                            @update:model-value="updateParameter(field.id, $event)"
                        />
                        <AppInput
                            v-else-if="field.tipo_campo === 'data'"
                            :model-value="String(parameterValue(field.id) ?? '')"
                            :label="parameterLabel(field)"
                            type="date"
                            @update:model-value="updateParameter(field.id, $event)"
                        />
                        <AppSelect
                            v-else-if="field.tipo_campo === 'sim_nao'"
                            :model-value="String(parameterValue(field.id) ?? '')"
                            :label="parameterLabel(field)"
                            @update:model-value="updateParameter(field.id, $event)"
                        >
                            <option value="">Selecione</option>
                            <option value="sim">Sim</option>
                            <option value="nao">Não</option>
                        </AppSelect>
                        <AppCheckbox
                            v-else-if="field.tipo_campo === 'checkbox_texto'"
                            :model-value="Boolean(parameterValue(field.id))"
                            :label="String(field.texto_checkbox || '').trim() || parameterLabel(field)"
                            @update:model-value="updateParameter(field.id, $event)"
                        />
                        <AppInput
                            v-else
                            :model-value="String(parameterValue(field.id) ?? '')"
                            :label="parameterLabel(field)"
                            @update:model-value="updateParameter(field.id, $event)"
                        />
                    </template>
                </div>
            </section>

            <AppTextarea
                :model-value="observation"
                label="Observacoes"
                rows="3"
                maxlength="220"
                placeholder="Ex.: ponto da carne, sem gelo, observacao para cozinha/bar"
                @update:model-value="emit('update:observation', $event)"
            />

            <div class="modifier-modal__actions">
                <AppButton variant="secondary" @click="emit('close')">Cancelar</AppButton>
                <AppButton @click="emit('submit')">Adicionar ao pedido</AppButton>
            </div>
        </div>
    </AppModal>
</template>

<style scoped>
.modifier-modal {
    display: grid;
    gap: 0.8rem;
}

.modifier-modal__line {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 0.7rem;
    flex-wrap: wrap;
}

.modifier-modal__label {
    margin: 0 0 0.4rem;
    color: var(--color-text-muted);
    font-size: 0.82rem;
}

.modifier-modal__price {
    display: grid;
    gap: 0.15rem;
    color: var(--color-text-muted);
    text-align: right;
    font-size: 0.82rem;
}

.modifier-modal__price strong {
    color: var(--color-primary);
    font-size: 1.04rem;
}

.modifier-modal__section {
    border: 1px solid var(--color-border);
    border-radius: 0.7rem;
    padding: 0.65rem;
    display: grid;
    gap: 0.38rem;
}

.modifier-modal__section h4 {
    margin: 0;
    color: var(--color-text);
    font-size: 0.88rem;
}

.modifier-modal__checks {
    display: grid;
    gap: 0.33rem;
}

.modifier-modal__check {
    border: 1px solid var(--color-border);
    border-radius: 0.58rem;
    padding: 0.44rem 0.48rem;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    color: var(--color-text);
    font-size: 0.84rem;
}

.modifier-modal__check small {
    margin-left: auto;
    color: var(--color-text-muted);
}

.modifier-modal__actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.52rem;
    flex-wrap: wrap;
}

.modifier-modal__classification-fields {
    display: grid;
    gap: 0.5rem;
}
</style>
