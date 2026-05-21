<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import AppCard from '../../components/ui/AppCard.vue';
import AppButton from '../../components/ui/AppButton.vue';
import SettingsPageHeader from '../../components/settings/SettingsPageHeader.vue';

const route = useRoute();

const title = computed(() => route.meta?.moduleTitle || 'Módulo');
const subtitle = computed(() => route.meta?.moduleSubtitle || 'Em preparação');
const hint = computed(() => route.meta?.moduleHint || 'Este módulo está sendo ajustado para o novo fluxo da retaguarda.');
const cta = computed(() => route.meta?.moduleCta || '');
</script>

<template>
    <div class="space-y-4">
        <SettingsPageHeader :title="title" :subtitle="subtitle">
            <template #actions>
                <AppButton v-if="cta" disabled>{{ cta }}</AppButton>
            </template>
        </SettingsPageHeader>

        <AppCard elevated class="p-8">
            <div class="ui-empty">
                <p class="ui-section-title">Em atualização</p>
                <p class="ui-page-subtitle">
                    {{ hint }}
                </p>
            </div>
        </AppCard>
    </div>
</template>
