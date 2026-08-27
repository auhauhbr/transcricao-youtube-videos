<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import AnonymousQuotaDialog from '../components/AnonymousQuotaDialog.vue';
import PublicLayout from '../layouts/PublicLayout.vue';

const { appName, extractUrl, anonymousQuota } = defineProps({
    appName: {
        type: String,
        required: true,
    },
    extractUrl: {
        type: String,
        required: true,
    },
    anonymousQuota: {
        type: Object,
        default: null,
    },
});

const benefits = ['Timestamps', 'Capítulos', 'TXT / Markdown', 'Organização'];
const videoUrlInput = ref(null);
const submitButton = ref(null);
const quotaDialog = ref(null);
const form = useForm({
    video_url: '',
});

const submit = () => {
    if (form.processing) {
        return;
    }

    form.post(extractUrl, {
        preserveScroll: true,
        onError: (errors) => {
            if (errors.anonymous_quota) {
                quotaDialog.value?.open(submitButton.value);
                return;
            }

            nextTick(() => videoUrlInput.value?.focus());
        },
    });
};

const quotaMessage = () => {
    if (!anonymousQuota) {
        return '';
    }

    if (anonymousQuota.remaining === anonymousQuota.limit) {
        return `${anonymousQuota.limit} transcrições gratuitas sem cadastro.`;
    }

    if (anonymousQuota.remaining === 1) {
        return '1 transcrição gratuita restante.';
    }

    if (anonymousQuota.remaining === 0) {
        return 'Limite gratuito utilizado. Entre ou crie uma conta para continuar.';
    }

    return `${anonymousQuota.remaining} transcrições gratuitas restantes.`;
};
</script>

<template>
    <Head title="Transforme vídeos em texto" />

    <PublicLayout :app-name="appName">
        <section id="inicio" class="bg-background">
            <div class="mx-auto max-w-5xl px-5 py-14 sm:px-8 sm:py-20 lg:px-10 lg:py-24">
                <div class="max-w-3xl">
                    <p class="ui-eyebrow mb-4 flex items-center gap-2.5">
                        <i class="bi bi-file-text" aria-hidden="true"></i> Conteúdo primeiro
                    </p>
                    <h1 class="max-w-4xl text-4xl font-semibold leading-[1.02] tracking-[-0.035em] text-foreground sm:text-5xl lg:text-6xl">
                        Transforme vídeos em texto.
                    </h1>
                    <p class="mt-5 max-w-2xl text-base leading-7 text-muted-foreground sm:text-lg">
                        Uma ferramenta para obter e organizar transcrições de vídeos do YouTube com clareza, contexto e estrutura.
                    </p>
                </div>

                <form class="ui-panel mt-9 max-w-3xl p-4 sm:p-5" :aria-busy="form.processing" @submit.prevent="submit">
                    <label for="video-url" class="mb-3 block text-sm font-semibold text-foreground">URL do vídeo</label>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <input
                            id="video-url"
                            ref="videoUrlInput"
                            v-model="form.video_url"
                            name="video_url"
                            type="url"
                            inputmode="url"
                            autocomplete="url"
                            placeholder="https://youtube.com/watch?v=..."
                            :aria-invalid="form.errors.video_url ? 'true' : 'false'"
                            :aria-describedby="form.errors.video_url ? 'video-url-error' : undefined"
                            class="ui-input h-12 min-w-0 flex-1 px-4 text-base placeholder:text-muted-foreground/70 disabled:cursor-wait disabled:opacity-70"
                            :disabled="form.processing"
                        />
                        <button
                            ref="submitButton"
                            type="submit"
                            class="ui-button-primary h-12 shrink-0 px-7"
                            :disabled="form.processing"
                        >
                            <i class="bi bi-file-earmark-text" aria-hidden="true"></i> {{ form.processing ? 'Enviando...' : 'Extrair' }}
                        </button>
                    </div>
                    <p v-if="form.errors.video_url" id="video-url-error" class="mt-3 text-sm font-medium text-destructive">
                        {{ form.errors.video_url }}
                    </p>
                    <p v-if="anonymousQuota" class="mt-3 text-sm text-muted-foreground">{{ quotaMessage() }}</p>
                </form>

                <AnonymousQuotaDialog
                    ref="quotaDialog"
                    :message="form.errors.anonymous_quota || 'Você utilizou suas 3 transcrições gratuitas. Entre ou crie uma conta para continuar.'"
                    @close="form.clearErrors('anonymous_quota')"
                />

                <ul class="mt-7 flex max-w-3xl flex-wrap gap-x-7 gap-y-3" aria-label="Benefícios">
                    <li v-for="benefit in benefits" :key="benefit" class="flex items-center gap-2 text-sm text-muted-foreground">
                        <i class="bi bi-check2 text-accent" aria-hidden="true"></i>
                        {{ benefit }}
                    </li>
                </ul>
            </div>
        </section>
    </PublicLayout>
</template>
