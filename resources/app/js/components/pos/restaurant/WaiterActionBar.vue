<script setup>
import { BringToFront, CheckCheck, FileCheck2, MessageSquareText, PlusCircle, SplitSquareVertical } from 'lucide-vue-next';
import AppButton from '../../ui/AppButton.vue';

const quickActions = Object.freeze([
    { id: 'send-kitchen', label: 'Enviar para cozinha/bar', icon: CheckCheck },
    { id: 'new-command', label: 'Nova comanda', icon: PlusCircle },
    { id: 'observation', label: 'Observacao', icon: MessageSquareText },
    { id: 'transfer-items', label: 'Transferir itens', icon: BringToFront },
    { id: 'merge-commands', label: 'Juntar fichas', icon: SplitSquareVertical },
    { id: 'conference', label: 'Conferencia', icon: FileCheck2 },
]);

const emit = defineEmits(['action']);
</script>

<template>
    <section class="waiter-action-bar ui-card">
        <AppButton
            v-for="action in quickActions"
            :key="action.id"
            variant="secondary"
            class="waiter-action-bar__btn"
            @click="emit('action', action.id)"
        >
            <component :is="action.icon" class="h-4 w-4" aria-hidden="true" />
            {{ action.label }}
        </AppButton>
    </section>
</template>

<style scoped>
.waiter-action-bar {
    padding: 0.68rem;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
    gap: 0.45rem;
}

.waiter-action-bar__btn {
    justify-content: flex-start;
}
</style>
