<script setup>
import AppButton from '../ui/AppButton.vue';

const emit = defineEmits(['finalize']);
</script>

<template>
    <div class="grid gap-2">
        <slot name="before" />
        <AppButton block @click="emit('finalize')">
            <slot>Finalizar Venda</slot>
        </AppButton>
    </div>
</template>
