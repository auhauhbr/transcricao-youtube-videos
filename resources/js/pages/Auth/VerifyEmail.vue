<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FlashToast from '../../components/FlashToast.vue';
import PublicLayout from '../../layouts/PublicLayout.vue';

const props = defineProps({
    appName: { type: String, required: true },
    flash: { type: Object, required: true },
    resendUrl: { type: String, required: true },
});

const form = useForm({});
const message = computed(() => props.flash.status === 'verification-link-sent'
    ? 'Enviamos um novo link de confirmação para seu email.'
    : null);
</script>

<template>
    <Head title="Confirme seu email">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <PublicLayout :app-name="appName">
        <FlashToast :flash-id="flash.id" :message="message" />
        <section class="flex flex-1 items-center bg-background px-5 py-10 sm:px-8">
            <div class="ui-panel mx-auto w-full max-w-lg p-6 sm:p-8">
                <p class="ui-eyebrow">Confirmação necessária</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight text-foreground">Confirme seu email</h1>
                <p class="mt-4 text-sm leading-6 text-muted-foreground">Enviamos um link de confirmação para seu endereço de email. Confirme-o para acessar sua Biblioteca.</p>
                <form class="mt-6" @submit.prevent="form.post(resendUrl)">
                    <button type="submit" class="ui-button-secondary" :disabled="form.processing">
                        <i class="bi bi-envelope" aria-hidden="true"></i> Reenviar link
                    </button>
                </form>
            </div>
        </section>
    </PublicLayout>
</template>
