<script setup>
defineProps({
    steps: {
        type: Array,
        default: () => [],
    },
    currentStep: {
        type: Number,
        default: 1,
    },
});
</script>

<template>
    <ol class="finalize-stepper">
        <li
            v-for="(step, index) in steps"
            :key="step.id"
            class="stepper-item"
            :class="{
                'is-active': step.id === currentStep,
                'is-done': step.id < currentStep,
            }"
        >
            <span class="stepper-bullet">{{ index + 1 }}</span>
            <span class="stepper-label">{{ step.label }}</span>
        </li>
    </ol>
</template>

<style scoped>
.finalize-stepper {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.6rem;
}

.stepper-item {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    padding: 0.48rem 0.62rem;
    color: var(--color-text-muted);
    background: color-mix(in srgb, var(--color-bg-elevated) 72%, var(--color-bg-surface));
    min-width: 0;
}

.stepper-bullet {
    width: 1.3rem;
    height: 1.3rem;
    border-radius: 999px;
    border: 1px solid currentColor;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    font-weight: 800;
    flex: 0 0 auto;
}

.stepper-label {
    font-size: 0.76rem;
    font-weight: 700;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.stepper-item.is-active {
    color: var(--color-primary);
    border-color: color-mix(in srgb, var(--color-primary) 48%, var(--color-border));
    background: color-mix(in srgb, var(--color-primary) 14%, var(--color-bg-surface));
}

.stepper-item.is-done {
    color: var(--color-success);
    border-color: color-mix(in srgb, var(--color-success) 44%, var(--color-border));
}

@media (max-width: 900px) {
    .finalize-stepper {
        grid-template-columns: 1fr;
    }
}
</style>
