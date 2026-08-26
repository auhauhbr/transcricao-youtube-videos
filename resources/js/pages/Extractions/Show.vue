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
    downloadUrl: {
        type: String,
        default: null,
    },
    libraryUrl: {
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
        only: ['extraction', 'video', 'transcript', 'failureMessage', 'downloadUrl', 'libraryUrl'],
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
        <TranscriptResult
            v-if="hasReadyResult"
            :transcript="transcript"
            :video="video"
            :download-url="downloadUrl"
            :library-url="libraryUrl"
        />

        <section v-else class="flex flex-1 items-center border-b border-border bg-background">
            <div class="mx-auto flex w-full max-w-3xl items-center px-5 py-16 sm:px-8 sm:py-20">
                <div class="ui-panel w-full p-6 sm:p-8">
                    <template v-if="extraction.status === 'pending'">
                        <p class="ui-eyebrow"><i class="bi bi-hourglass-split mr-1" aria-hidden="true"></i> Na fila</p>
                        <h1 class="mt-3 text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">Preparando sua transcrição</h1>
                        <p class="mt-4 text-base leading-7 text-muted-foreground" aria-live="polite">
                            A solicitação está aguardando processamento.
                        </p>
                    </template>

                    <template v-else-if="extraction.status === 'processing'">
                        <div class="flex items-center gap-3 text-accent" aria-hidden="true">
                            <i class="bi bi-arrow-repeat motion-safe:animate-spin" aria-hidden="true"></i>
                            <span class="text-xs font-semibold uppercase tracking-[0.18em]">Em processamento</span>
                        </div>
                        <h1 class="mt-3 text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">Processando transcrição</h1>
                        <p class="mt-4 text-base leading-7 text-muted-foreground" aria-live="polite">
                            Isso normalmente leva alguns segundos.
                        </p>
                    </template>

                    <template v-else>
                        <p class="ui-eyebrow text-destructive"><i class="bi bi-exclamation-circle mr-1" aria-hidden="true"></i> Não concluída</p>
                        <h1 class="mt-3 text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                            Não foi possível obter a transcrição
                        </h1>
                        <p class="mt-4 text-base leading-7 text-muted-foreground" role="alert">
                            {{ isUnknown || !failureMessage ? 'Ocorreu um erro inesperado. Tente novamente mais tarde.' : failureMessage }}
                        </p>
                        <Link
                            href="/"
                            class="ui-button-primary mt-7"
                        >
                            Voltar à página inicial
                        </Link>
                    </template>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
