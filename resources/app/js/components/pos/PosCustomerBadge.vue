<script setup>
defineProps({
    label: {
        type: String,
        default: 'Cliente balcão',
    },
});
</script>

<template>
    <span class="ui-badge ui-badge-default">{{ label }}</span>
</template>
