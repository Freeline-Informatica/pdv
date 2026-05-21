<script setup>
import AppPageHeader from '../ui/AppPageHeader.vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <AppPageHeader :title="props.title" :subtitle="props.subtitle">
        <template #actions>
            <slot name="actions" />
        </template>
    </AppPageHeader>
</template>
