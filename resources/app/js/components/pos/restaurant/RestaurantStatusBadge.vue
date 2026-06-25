<script setup>
import { computed } from 'vue';
import AppBadge from '../../ui/AppBadge.vue';

const props = defineProps({
    status: {
        type: String,
        default: 'default',
    },
});

const statusMeta = computed(() => {
    const map = {
        novo: { label: 'Novo', variant: 'warning' },
        em_preparo: { label: 'Em preparo', variant: 'default' },
        pronto: { label: 'Pronto', variant: 'success' },
        entregue: { label: 'Entregue', variant: 'default' },
        opened: { label: 'Aberta', variant: 'success' },
        em_atendimento: { label: 'Em atendimento', variant: 'success' },
        aguardando_produção: { label: 'Aguardando produção', variant: 'warning' },
        parcialmente_entregue: { label: 'Parcialmente entregue', variant: 'default' },
        em_conferencia: { label: 'Em conferência', variant: 'warning' },
        aguardando_pagamento: { label: 'Aguardando pagamento', variant: 'danger' },
        paga: { label: 'Paga', variant: 'default' },
        cancelada: { label: 'Cancelada', variant: 'danger' },
        closed: { label: 'Fechada', variant: 'default' },
        delayed: { label: 'Atrasada', variant: 'danger' },
        out_of_stock: { label: 'Sem estoque', variant: 'danger' },
        default: { label: 'Ativo', variant: 'default' },
    };

    return map[props.status] || map.default;
});
</script>

<template>
    <AppBadge :variant="statusMeta.variant">{{ statusMeta.label }}</AppBadge>
</template>
