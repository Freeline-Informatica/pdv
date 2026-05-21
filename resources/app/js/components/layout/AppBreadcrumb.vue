<script setup>
defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <nav aria-label="Breadcrumb">
        <ol class="flex items-center gap-2 text-sm text-muted">
            <li v-for="(item, index) in items" :key="`${item.label}-${index}`" class="inline-flex items-center gap-2">
                <span v-if="index !== 0" aria-hidden="true">/</span>
                <span :class="index === items.length - 1 ? 'text-main font-semibold' : ''">{{ item.label }}</span>
            </li>
        </ol>
    </nav>
</template>
