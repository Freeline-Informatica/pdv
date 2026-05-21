<template>
    <div class="grid grid-cols-3 gap-2">
        <button v-for="n in 9" :key="n" type="button" class="ui-btn ui-btn-secondary">{{ n }}</button>
        <button type="button" class="ui-btn ui-btn-secondary">0</button>
        <button type="button" class="ui-btn ui-btn-danger col-span-2">Limpar</button>
    </div>
</template>
