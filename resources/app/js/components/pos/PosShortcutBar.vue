<script setup>
const shortcuts = ['Cliente', 'Desconto', 'Observação', 'Segurar venda', 'Consultar preço', 'Abrir gaveta'];
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <button v-for="item in shortcuts" :key="item" type="button" class="pos-shortcut">
            {{ item }}
        </button>
    </div>
</template>
