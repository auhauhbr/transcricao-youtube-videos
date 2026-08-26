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
        <section id="inicio" class="border-b border-border bg-background">
            <div class="mx-auto max-w-7xl px-5 pb-12 pt-14 sm:px-8 sm:pb-16 sm:pt-20 lg:px-10 lg:pb-20 lg:pt-24">
                <div class="max-w-5xl">
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

                <form class="mt-9 max-w-5xl" :aria-busy="form.processing" @submit.prevent="submit">
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

                <ul class="mt-7 flex max-w-5xl flex-wrap gap-x-7 gap-y-3" aria-label="Benefícios planejados">
                    <li v-for="benefit in benefits" :key="benefit" class="flex items-center gap-2 text-sm text-muted-foreground">
                        <i class="bi bi-check2 text-accent" aria-hidden="true"></i>
                        {{ benefit }}
                    </li>
                </ul>
            </div>
        </section>

        <section class="border-b border-border bg-muted" aria-labelledby="preview-title">
            <div class="mx-auto max-w-7xl px-5 py-12 sm:px-8 sm:py-16 lg:px-10">
                <div class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-accent">Prévia conceitual</p>
                        <h2 id="preview-title" class="mt-3 text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                            Texto organizado para leitura.
                        </h2>
                    </div>
                    <p class="max-w-md text-sm leading-6 text-muted-foreground">Uma representação estática da experiência de transcrição planejada.</p>
                </div>

                <div class="ui-panel">
                    <div class="flex h-11 items-center justify-between border-b border-border px-4 sm:px-5">
                        <div class="flex items-center gap-1.5" aria-hidden="true">
                            <span class="size-2 border border-border bg-muted"></span>
                            <span class="size-2 border border-border bg-muted"></span>
                            <span class="size-2 border border-border bg-accent"></span>
                        </div>
                        <span class="text-[11px] font-medium uppercase tracking-[0.16em] text-muted-foreground">Exemplo de interface</span>
                    </div>

                    <div class="grid md:grid-cols-[220px_1fr] lg:grid-cols-[260px_1fr]">
                        <aside class="hidden border-r border-border p-6 md:block" aria-label="Capítulos do exemplo">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Capítulos</p>
                            <ol class="mt-5 space-y-1 text-sm">
                                <li class="border-l-2 border-accent bg-muted px-3 py-2.5 font-medium text-foreground">00:00 Introdução</li>
                                <li class="border-l-2 border-transparent px-3 py-2.5 text-muted-foreground">01:22 Preparação</li>
                                <li class="border-l-2 border-transparent px-3 py-2.5 text-muted-foreground">05:48 Organização</li>
                            </ol>
                        </aside>

                        <article class="p-6 sm:p-8 lg:p-10">
                            <header class="border-b border-border pb-7">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-accent">Vídeo demonstrativo</p>
                                <h3 class="mt-3 text-2xl font-semibold tracking-tight text-foreground">Como organizar uma pesquisa em vídeo</h3>
                                <p class="mt-2 text-sm text-muted-foreground">Canal de exemplo · 24:20</p>
                            </header>

                            <div class="divide-y divide-border">
                                <section class="grid gap-3 py-7 sm:grid-cols-[72px_1fr] sm:gap-6">
                                    <time class="font-mono text-xs font-semibold text-accent">00:00</time>
                                    <div>
                                        <h4 class="text-sm font-semibold uppercase tracking-[0.12em] text-foreground">Introdução</h4>
                                        <p class="mt-3 max-w-3xl text-sm leading-7 text-muted-foreground">
                                            Neste trecho demonstrativo, o conteúdo aparece em blocos legíveis e conectado ao momento correspondente do vídeo.
                                        </p>
                                    </div>
                                </section>
                                <section class="grid gap-3 py-7 sm:grid-cols-[72px_1fr] sm:gap-6">
                                    <time class="font-mono text-xs font-semibold text-accent">01:22</time>
                                    <div>
                                        <h4 class="text-sm font-semibold uppercase tracking-[0.12em] text-foreground">Preparação</h4>
                                        <p class="mt-3 max-w-3xl text-sm leading-7 text-muted-foreground">
                                            Timestamps e capítulos ajudam a percorrer textos longos sem perder a relação com a fonte original.
                                        </p>
                                    </div>
                                </section>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
