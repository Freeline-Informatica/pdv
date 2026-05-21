<script setup>
defineProps({
    methods: {
        type: Array,
        default: () => [],
    },
    selectedMethodId: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['select']);
</script>

<template>
    <div class="payment-grid">
        <button
            v-for="method in methods"
            :key="method.id"
            type="button"
            class="payment-method-btn"
            :class="{ 'is-active': method.id === selectedMethodId }"
            @click="emit('select', method.id)"
        >
            <span class="payment-method-name">{{ method.nome }}</span>
            <small class="payment-method-type">{{ method.tipo || 'outro' }}</small>
        </button>
    </div>
</template>

<style scoped>
.payment-grid {
    display: grid;
    gap: 0.6rem;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
}

.payment-method-btn {
    border-radius: var(--radius-sm);
    border: 1px solid var(--color-border);
    background: color-mix(in srgb, var(--color-bg-elevated) 74%, var(--color-bg-surface));
    color: var(--color-text);
    padding: 0.62rem 0.7rem;
    text-align: left;
    transition: border-color var(--transition-fast), transform var(--transition-fast), background var(--transition-fast);
}

.payment-method-btn:hover {
    border-color: var(--color-border-strong);
    transform: translateY(-1px);
}

.payment-method-btn:focus-visible {
    border-color: color-mix(in srgb, var(--color-primary) 60%, var(--color-border));
    transform: translateY(-1px);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 24%, transparent);
}

.payment-method-btn.is-active {
    border-color: color-mix(in srgb, var(--color-primary) 58%, var(--color-border));
    background: color-mix(in srgb, var(--color-primary) 14%, var(--color-bg-surface));
}

.payment-method-name {
    display: block;
    font-size: 0.86rem;
    font-weight: 800;
}

.payment-method-type {
    font-size: 0.72rem;
    color: var(--color-text-muted);
}
</style>
