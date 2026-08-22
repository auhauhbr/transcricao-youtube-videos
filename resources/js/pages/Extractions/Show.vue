<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import TranscriptResult from '../../components/TranscriptResult.vue';
import PublicLayout from '../../layouts/PublicLayout.vue';

const props = defineProps({
    appName: {
        type: String,
        required: true,
    },
    extraction: {
        type: Object,
        required: true,
    },
    video: {
        type: Object,
        required: true,
    },
    transcript: {
        type: Object,
        default: null,
    },
    failureMessage: {
        type: String,
        default: null,
    },
});

const pollingStatuses = new Set(['pending', 'processing']);
const knownStatuses = new Set(['pending', 'processing', 'ready', 'failed']);
const shouldPoll = computed(() => pollingStatuses.has(props.extraction.status));
const isUnknown = computed(() => !knownStatuses.has(props.extraction.status));
const hasReadyResult = computed(() => props.extraction.status === 'ready' && props.transcript !== null);
const pageTitle = computed(() => {
    if (hasReadyResult.value) {
        return props.video.title;
    }

    if (props.extraction.status === 'pending') {
        return 'Preparando transcrição';
    }

    if (props.extraction.status === 'processing') {
        return 'Processando transcrição';
    }

    return 'Não foi possível obter a transcrição';
});

let pollTimer = null;
let pollInFlight = false;
let componentUnmounted = false;

const clearPollTimer = () => {
    if (pollTimer !== null) {
        window.clearTimeout(pollTimer);
        pollTimer = null;
    }
};

const schedulePoll = () => {
    clearPollTimer();

    if (componentUnmounted || pollInFlight || !shouldPoll.value || document.visibilityState === 'hidden') {
        return;
    }

    pollTimer = window.setTimeout(runPoll, 2000);
};

const runPoll = () => {
    clearPollTimer();

    if (componentUnmounted || pollInFlight || !shouldPoll.value || document.visibilityState === 'hidden') {
        schedulePoll();
        return;
    }

    pollInFlight = true;
    router.reload({
        only: ['extraction', 'video', 'transcript', 'failureMessage'],
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            pollInFlight = false;
            schedulePoll();
        },
    });
};

const handleVisibilityChange = () => {
    if (document.visibilityState === 'hidden') {
        clearPollTimer();
        return;
    }

    schedulePoll();
};

watch(
    () => props.extraction.status,
    () => {
        if (shouldPoll.value) {
            schedulePoll();
        } else {
            clearPollTimer();
        }
    },
);

onMounted(() => {
    document.addEventListener('visibilitychange', handleVisibilityChange);
    schedulePoll();
});

onBeforeUnmount(() => {
    componentUnmounted = true;
    clearPollTimer();
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});
</script>

<template>
    <Head :title="pageTitle">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <PublicLayout :app-name="appName">
        <TranscriptResult v-if="hasReadyResult" :transcript="transcript" :video="video" />

        <section v-else class="border-b border-border bg-background">
            <div class="mx-auto flex min-h-[60vh] max-w-3xl items-center px-5 py-16 sm:px-8 sm:py-24">
                <div class="w-full border border-border bg-card p-7 sm:p-10">
                    <template v-if="extraction.status === 'pending'">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-accent">Na fila</p>
                        <h1 class="mt-4 text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">Preparando sua transcrição</h1>
                        <p class="mt-4 text-base leading-7 text-muted-foreground" aria-live="polite">
                            A solicitação está aguardando processamento.
                        </p>
                    </template>

                    <template v-else-if="extraction.status === 'processing'">
                        <div class="flex items-center gap-3 text-accent" aria-hidden="true">
                            <span class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent"></span>
                            <span class="text-xs font-semibold uppercase tracking-[0.18em]">Em processamento</span>
                        </div>
                        <h1 class="mt-4 text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">Processando transcrição</h1>
                        <p class="mt-4 text-base leading-7 text-muted-foreground" aria-live="polite">
                            Isso normalmente leva alguns segundos.
                        </p>
                    </template>

                    <template v-else>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-destructive">Não concluída</p>
                        <h1 class="mt-4 text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">
                            Não foi possível obter a transcrição
                        </h1>
                        <p class="mt-4 text-base leading-7 text-muted-foreground" role="alert">
                            {{ isUnknown || !failureMessage ? 'Ocorreu um erro inesperado. Tente novamente mais tarde.' : failureMessage }}
                        </p>
                        <Link
                            href="/"
                            class="mt-8 inline-flex h-11 items-center bg-accent px-5 text-sm font-semibold text-accent-foreground transition-colors hover:bg-accent-hover"
                        >
                            Voltar à página inicial
                        </Link>
                    </template>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
