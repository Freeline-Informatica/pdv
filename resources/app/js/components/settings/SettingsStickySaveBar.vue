<script setup>
import AppStickyActionBar from '../layout/AppStickyActionBar.vue';
</script>

<template>
    <AppStickyActionBar>
        <div class="flex items-center justify-between gap-3">
            <slot name="info" />
            <div class="flex items-center gap-2">
                <slot name="actions" />
            </div>
        </div>
    </AppStickyActionBar>
</template>
