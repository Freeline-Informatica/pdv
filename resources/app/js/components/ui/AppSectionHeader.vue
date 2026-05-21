<script setup>
defineProps({
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
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
            <h2 class="ui-section-title">{{ title }}</h2>
            <p v-if="subtitle" class="ui-section-subtitle">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.actions" class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
