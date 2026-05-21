<script setup>
import PosCartSummary from './PosCartSummary.vue';

defineProps({
    subtotal: {
        type: [String, Number],
        default: '',
    },
    saleContext: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <PosCartSummary :subtotal="subtotal" :sale-context="saleContext" />
</template>
