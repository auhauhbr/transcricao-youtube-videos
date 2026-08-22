<script setup>
import { computed, ref } from 'vue';
import { formatTimestamp } from '../utils/formatTimestamp.js';
import VideoSummaryCard from './VideoSummaryCard.vue';

const props = defineProps({
    transcript: {
        type: Object,
        required: true,
    },
    video: {
        type: Object,
        required: true,
    },
});

const copyMessage = ref('');
const copyFailed = ref(false);
const fullTranscript = computed(() => props.transcript.segments.map((segment) => segment.text).join('\n\n'));
const languageLabel = computed(() => props.transcript.languageName || props.transcript.languageCode);
const sourceLabel = computed(() => (props.transcript.source === 'manual' ? 'Legendas do vídeo' : 'Legendas automáticas'));

const segmentAnchorFor = (startMs) => {
    const segment = props.transcript.segments.find((item) => item.startMs >= startMs) ?? props.transcript.segments.at(-1);

    return segment ? `#segment-${segment.position}` : '#transcript-title';
};

const youtubeTimestampUrl = (startMs) => {
    const seconds = Math.max(0, Math.floor(Number(startMs) / 1000));

    return `${props.video.youtubeUrl}&t=${seconds}s`;
};

const copyTranscript = async () => {
    copyMessage.value = '';
    copyFailed.value = false;

    try {
        if (!navigator.clipboard?.writeText) {
            throw new Error('Clipboard API is unavailable.');
        }

        await navigator.clipboard.writeText(fullTranscript.value);
        copyMessage.value = 'Transcrição copiada.';
    } catch {
        copyFailed.value = true;
        copyMessage.value = 'Não foi possível copiar. Selecione o texto manualmente.';
    }
};
</script>

<template>
    <article class="flex-1 bg-background">
        <div class="mx-auto max-w-7xl px-5 py-8 sm:px-8 sm:py-10 lg:px-10 lg:py-12">
            <header class="border-b border-border pb-7">
                <a href="/" class="inline-flex text-sm font-medium text-muted-foreground transition-colors hover:text-foreground">← Voltar</a>
                <div class="mt-6 min-w-0 max-w-5xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-accent">Transcrição pronta</p>
                    <h1 class="mt-3 text-3xl font-semibold leading-tight tracking-[-0.035em] text-foreground sm:text-4xl">
                        {{ video.title }}
                    </h1>
                    <p class="mt-3 text-sm text-muted-foreground sm:text-base">
                        {{ video.channelName }} · {{ formatTimestamp(video.durationSeconds * 1000) }} · {{ languageLabel }}
                    </p>
                </div>
            </header>

            <div class="mt-7 grid items-start gap-6 lg:grid-cols-[320px_minmax(0,1fr)] lg:gap-8">
                <aside class="space-y-5 lg:sticky lg:top-24">
                    <VideoSummaryCard :video="video" />

                    <nav v-if="transcript.chapters.length" class="border border-border bg-card p-5" aria-labelledby="chapters-title">
                        <div class="flex items-center justify-between gap-4">
                            <h2 id="chapters-title" class="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Capítulos</h2>
                            <span class="text-xs text-muted-foreground">{{ transcript.chapters.length }}</span>
                        </div>
                        <ol class="mt-3 divide-y divide-border">
                            <li v-for="chapter in transcript.chapters" :key="chapter.position">
                                <a
                                    :href="segmentAnchorFor(chapter.startMs)"
                                    class="grid grid-cols-[48px_minmax(0,1fr)] gap-2 py-3 text-sm text-muted-foreground transition-colors hover:text-foreground"
                                >
                                    <span class="font-mono text-xs font-semibold text-accent">{{ formatTimestamp(chapter.startMs) }}</span>
                                    <span class="leading-5">{{ chapter.title }}</span>
                                </a>
                            </li>
                        </ol>
                    </nav>
                </aside>

                <section class="min-w-0 border border-border bg-card" aria-labelledby="transcript-title">
                    <div class="flex flex-col gap-5 border-b border-border p-5 sm:flex-row sm:items-start sm:justify-between sm:p-6">
                        <div>
                            <h2 id="transcript-title" class="text-xl font-semibold tracking-tight text-foreground">Transcrição completa</h2>
                            <p class="mt-1.5 text-xs leading-5 text-muted-foreground">
                                {{ languageLabel }} · {{ sourceLabel }} · {{ transcript.wordCount.toLocaleString('pt-BR') }} palavras
                            </p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-10 shrink-0 items-center justify-center bg-accent px-4 text-sm font-semibold text-accent-foreground transition-colors hover:bg-accent-hover"
                            @click="copyTranscript"
                        >
                            Copiar tudo
                        </button>
                    </div>

                    <p
                        v-if="copyMessage"
                        class="border-b border-border px-5 py-3 text-sm sm:px-6"
                        :class="copyFailed ? 'text-destructive' : 'text-muted-foreground'"
                        aria-live="polite"
                    >
                        {{ copyMessage }}
                    </p>

                    <div class="divide-y divide-border lg:max-h-[calc(100vh-15rem)] lg:min-h-[30rem] lg:overflow-y-auto">
                        <div
                            v-for="segment in transcript.segments"
                            :id="`segment-${segment.position}`"
                            :key="segment.position"
                            class="scroll-mt-24 grid grid-cols-[58px_minmax(0,1fr)] gap-3 px-4 py-3.5 sm:grid-cols-[70px_minmax(0,1fr)] sm:gap-5 sm:px-6 sm:py-4"
                        >
                            <a
                                :href="youtubeTimestampUrl(segment.startMs)"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="h-fit font-mono text-xs font-semibold text-accent hover:underline"
                                :aria-label="`Abrir vídeo em ${formatTimestamp(segment.startMs)}`"
                            >
                                {{ formatTimestamp(segment.startMs) }}
                            </a>
                            <p class="min-w-0 text-sm leading-6 text-foreground/90 sm:text-[15px] sm:leading-7">{{ segment.text }}</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </article>
</template>
