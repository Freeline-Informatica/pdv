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
    <header class="ui-page-header">
        <div>
            <h1 class="ui-page-title">{{ title }}</h1>
            <p v-if="subtitle" class="ui-page-subtitle">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.actions" class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
