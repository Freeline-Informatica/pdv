<script setup>
import AppEmptyState from '../../ui/AppEmptyState.vue';

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
    <AppEmptyState :title="title" :description="description">
        <template v-if="$slots.actions" #actions>
            <slot name="actions" />
        </template>
    </AppEmptyState>
</template>
