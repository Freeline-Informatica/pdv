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
    widthClass: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close']);
</script>

<template>
    <div v-if="open" class="ui-modal-backdrop" @click.self="emit('close')">
        <div class="ui-modal-panel" data-nav-region="modal" :class="widthClass">
            <div class="flex items-start justify-between gap-3 mb-4">
                <h3 v-if="title" class="text-lg font-bold text-main">{{ title }}</h3>
                <div class="ml-auto flex items-center gap-2">
                    <slot name="header-actions" />
                    <button class="ui-btn ui-btn-ghost" type="button" @click="emit('close')">Fechar</button>
                </div>
            </div>
            <slot />
        </div>
    </div>
</template>
