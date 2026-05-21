<script setup>
defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close']);
</script>

<template>
    <div v-if="open" class="ui-modal-backdrop" @click.self="emit('close')">
        <div class="ml-auto flex h-full w-full max-w-lg flex-col border-l border-[var(--color-border)] bg-[var(--color-bg-surface)] p-5 shadow-[var(--shadow-lg)]">
            <div class="mb-4 flex shrink-0 items-center justify-between gap-3">
                <h3 class="text-lg font-bold text-main">{{ title }}</h3>
                <button class="ui-btn ui-btn-ghost" type="button" @click="emit('close')">Fechar</button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto">
                <slot />
            </div>
        </div>
    </div>
</template>
