<script setup>
defineProps({
    title: {
        type: String,
        default: 'Nenhum registro encontrado',
    },
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="ui-empty">
        <p class="ui-section-title">{{ title }}</p>
        <p v-if="description" class="ui-page-subtitle">{{ description }}</p>
        <div v-if="$slots.actions" class="mt-4">
            <slot name="actions" />
        </div>
    </div>
</template>
