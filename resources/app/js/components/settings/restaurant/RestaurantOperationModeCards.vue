<script setup>
import { CheckCircle2, Blend, WandSparkles } from 'lucide-vue-next';
import { restaurantOperationModes } from '../../../lib/restaurantParameters';

const props = defineProps({
    modelValue: {
        type: String,
        default: 'automatic',
    },
});

const emit = defineEmits(['update:modelValue']);

const modeIcons = Object.freeze({
    automatic: WandSparkles,
    manual: CheckCircle2,
    hybrid: Blend,
});

function selectMode(mode) {
    emit('update:modelValue', mode);
}
</script>

<template>
    <div class="restaurant-mode-grid">
        <button
            v-for="mode in restaurantOperationModes"
            :key="mode.id"
            type="button"
            class="restaurant-mode-card"
            :class="{ 'is-active': modelValue === mode.id }"
            @click="selectMode(mode.id)"
        >
            <div class="restaurant-mode-card__head">
                <span class="restaurant-mode-card__icon" :class="{ 'is-active': modelValue === mode.id }">
                    <component :is="modeIcons[mode.id]" class="h-4 w-4" aria-hidden="true" />
                </span>
                <span class="restaurant-mode-card__title">{{ mode.title }}</span>
                <CheckCircle2 v-if="modelValue === mode.id" class="h-4 w-4 restaurant-mode-card__check" aria-hidden="true" />
            </div>
            <p class="restaurant-mode-card__description">{{ mode.description }}</p>
        </button>
    </div>
</template>

<style scoped>
.restaurant-mode-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(14.5rem, 1fr));
    gap: 0.75rem;
}

.restaurant-mode-card {
    text-align: left;
    border-radius: var(--radius-md);
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 45%, transparent);
    background: color-mix(in srgb, var(--color-bg-surface) 88%, transparent);
    padding: 0.95rem;
    display: grid;
    gap: 0.65rem;
    transition: all var(--transition-fast);
}

.restaurant-mode-card:hover {
    border-color: color-mix(in srgb, var(--color-primary) 45%, transparent);
    background: color-mix(in srgb, var(--color-primary) 10%, var(--color-bg-surface));
}

.restaurant-mode-card.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 58%, transparent);
    background: color-mix(in srgb, var(--color-primary) 14%, var(--color-bg-surface));
    box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 22%, transparent);
}

.restaurant-mode-card__head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.restaurant-mode-card__icon {
    width: 1.85rem;
    height: 1.85rem;
    border-radius: var(--radius-sm);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid color-mix(in srgb, var(--color-border-strong) 45%, transparent);
    color: var(--color-text-muted);
}

.restaurant-mode-card__icon.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 50%, transparent);
    color: var(--color-primary);
    background: color-mix(in srgb, var(--color-primary) 16%, transparent);
}

.restaurant-mode-card__title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--color-text);
}

.restaurant-mode-card__check {
    margin-left: auto;
    color: var(--color-primary);
}

.restaurant-mode-card__description {
    margin: 0;
    font-size: 0.84rem;
    line-height: 1.45;
    color: var(--color-text-muted);
}
</style>
