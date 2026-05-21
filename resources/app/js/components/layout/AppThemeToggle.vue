<script setup>
import { computed } from 'vue';
import AppIconButton from '../ui/AppIconButton.vue';
import { useThemeStore } from '../../stores/theme';

const { isDark, mode, toggleTheme } = useThemeStore();

const title = computed(() => {
    if (mode.value === 'system') {
        return isDark.value
            ? 'Tema do sistema: escuro. Clique para fixar no claro'
            : 'Tema do sistema: claro. Clique para fixar no escuro';
    }

    return isDark.value ? 'Ativar tema claro' : 'Ativar tema escuro';
});

const icon = computed(() => {
    if (mode.value === 'system') return '◐';
    return isDark.value ? '☀' : '☾';
});
</script>

<template>
    <AppIconButton :title="title" @click="toggleTheme">
        <span aria-hidden="true">{{ icon }}</span>
    </AppIconButton>
</template>
