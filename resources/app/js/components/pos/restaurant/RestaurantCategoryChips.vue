<script setup>
import AppChip from '../../ui/AppChip.vue';

defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    activeCategory: {
        type: String,
        default: 'todos',
    },
});

const emit = defineEmits(['update:activeCategory']);
</script>

<template>
    <div class="restaurant-categories ui-card">
        <AppChip :active="activeCategory === 'todos'" @click="emit('update:activeCategory', 'todos')">Todos</AppChip>
        <AppChip
            v-for="category in categories"
            :key="category.id"
            :active="activeCategory === category.id"
            @click="emit('update:activeCategory', category.id)"
        >
            {{ category.nome }}
        </AppChip>
    </div>
</template>

<style scoped>
.restaurant-categories {
    padding: 0.55rem;
    display: flex;
    gap: 0.45rem;
    overflow-x: auto;
}
</style>
