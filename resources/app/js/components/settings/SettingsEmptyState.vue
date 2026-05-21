<script setup>
import AppEmptyState from '../ui/AppEmptyState.vue';

const props = defineProps({
    title: {
        type: String,
        default: 'Nenhum item encontrado',
    },
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <AppEmptyState :title="props.title" :description="props.description">
        <template #actions>
            <slot name="actions" />
        </template>
    </AppEmptyState>
</template>
