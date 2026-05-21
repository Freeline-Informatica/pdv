<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../lib/api';
import { setAuthSession } from '../lib/auth';
import AppInput from '../components/ui/AppInput.vue';
import AppButton from '../components/ui/AppButton.vue';
import AppCard from '../components/ui/AppCard.vue';
import AppThemeToggle from '../components/layout/AppThemeToggle.vue';

const router = useRouter();
const form = reactive({
    email: '',
    password: '',
});
const loading = ref(false);
const error = ref('');

async function submit(destination = 'pos') {
    error.value = '';
    loading.value = true;

    try {
        const { data } = await api.post('/auth/login', form);
        setAuthSession(data.token, data.user);
        await router.push(destination === 'backoffice' ? '/configuracoes' : '/selecionar-terminal');
    } catch (err) {
        error.value = err?.response?.data?.message ?? 'Falha ao entrar.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="fixed top-4 right-4">
            <AppThemeToggle />
        </div>

        <AppCard class="w-full max-w-md p-6" elevated>
            <img :src="'/logo.png'" alt="Simples PDV" class="mx-auto mb-4 h-16 w-auto object-contain" />
            <h1 class="text-2xl font-black text-main">Acesso ao Sistema</h1>
            <p class="text-sm text-muted mt-1">Entre para acessar o PDV e as configurações.</p>

            <form class="mt-6 space-y-4" @submit.prevent="submit('pos')">
                <AppInput
                    v-model="form.email"
                    type="email"
                    label="E-mail"
                    required
                    placeholder="seu@email.com"
                />

                <AppInput
                    v-model="form.password"
                    type="password"
                    label="Senha"
                    required
                    placeholder="••••••••"
                />

                <p v-if="error" class="text-sm text-danger">{{ error }}</p>

                <AppButton type="submit" :loading="loading" block>
                    Entrar
                </AppButton>
                <button
                    type="button"
                    class="w-full text-sm font-semibold text-[var(--color-primary)] hover:underline disabled:opacity-60 disabled:no-underline"
                    :disabled="loading"
                    @click="submit('backoffice')"
                >
                    Acessar a retaguarda
                </button>
            </form>
        </AppCard>
    </div>
</template>
