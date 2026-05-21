<script setup>
import AppCard from '../ui/AppCard.vue';
import AppSectionHeader from '../ui/AppSectionHeader.vue';

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
    <AppCard>
        <AppSectionHeader :title="props.title" :subtitle="props.subtitle" />
        <slot />
    </AppCard>
</template>
