<script setup>
import AppDrawer from '../ui/AppDrawer.vue';

defineProps({
    open: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);
</script>

<template>
    <AppDrawer :open="open" title="Pagamento" @close="emit('close')">
        <slot />
    </AppDrawer>
</template>
