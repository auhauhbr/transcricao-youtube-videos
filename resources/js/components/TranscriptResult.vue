<script setup>
import { computed, ref } from 'vue';
import { formatTimestamp } from '../utils/formatTimestamp.js';

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
    <article class="bg-background">
        <header class="border-b border-border">
            <div class="mx-auto max-w-7xl px-5 py-10 sm:px-8 sm:py-14 lg:px-10">
                <a href="/" class="inline-flex text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"> ← Voltar </a>

                <div class="mt-8 flex flex-col gap-7 lg:flex-row lg:items-end lg:justify-between">
                    <div class="min-w-0 max-w-4xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-accent">Transcrição</p>
                        <h1 class="mt-3 text-3xl font-semibold tracking-[-0.035em] text-foreground sm:text-4xl lg:text-5xl">
                            {{ video.title }}
                        </h1>
                        <p class="mt-4 text-sm text-muted-foreground sm:text-base">
                            {{ video.channelName }} · {{ formatTimestamp(video.durationSeconds * 1000) }} · {{ languageLabel }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <a
                            :href="video.youtubeUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex h-11 items-center border border-border bg-card px-4 text-sm font-semibold text-foreground transition-colors hover:bg-muted"
                        >
                            Abrir no YouTube
                        </a>
                        <button
                            type="button"
                            class="inline-flex h-11 items-center bg-accent px-4 text-sm font-semibold text-accent-foreground transition-colors hover:bg-accent-hover"
                            @click="copyTranscript"
                        >
                            Copiar tudo
                        </button>
                    </div>
                </div>

                <p
                    v-if="copyMessage"
                    class="mt-4 text-sm"
                    :class="copyFailed ? 'text-destructive' : 'text-muted-foreground'"
                    aria-live="polite"
                >
                    {{ copyMessage }}
                </p>
            </div>
        </header>

        <div class="mx-auto grid max-w-7xl" :class="transcript.chapters.length ? 'lg:grid-cols-[250px_minmax(0,1fr)]' : ''">
            <nav
                v-if="transcript.chapters.length"
                class="border-b border-border bg-muted px-5 py-8 sm:px-8 lg:border-b-0 lg:border-r lg:px-7 lg:py-12"
                aria-labelledby="chapters-title"
            >
                <h2 id="chapters-title" class="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Capítulos</h2>
                <ol class="mt-5 grid gap-1 sm:grid-cols-3 lg:grid-cols-1">
                    <li v-for="chapter in transcript.chapters" :key="chapter.position">
                        <a
                            :href="segmentAnchorFor(chapter.startMs)"
                            class="grid grid-cols-[48px_1fr] gap-2 border-l-2 border-transparent px-3 py-2.5 text-sm text-muted-foreground transition-colors hover:border-accent hover:bg-card hover:text-foreground"
                        >
                            <span class="font-mono text-xs text-accent">{{ formatTimestamp(chapter.startMs) }}</span>
                            <span>{{ chapter.title }}</span>
                        </a>
                    </li>
                </ol>
            </nav>

            <section class="min-w-0 px-5 py-8 sm:px-8 sm:py-12 lg:px-12 lg:py-14" aria-labelledby="transcript-title">
                <div class="border-b border-border pb-6">
                    <h2 id="transcript-title" class="text-2xl font-semibold tracking-tight text-foreground">Transcrição completa</h2>
                    <p class="mt-2 text-sm text-muted-foreground">Texto segmentado e conectado aos timestamps do vídeo.</p>
                </div>

                <div class="divide-y divide-border">
                    <div
                        v-for="segment in transcript.segments"
                        :id="`segment-${segment.position}`"
                        :key="segment.position"
                        class="scroll-mt-24 grid gap-3 py-7 sm:grid-cols-[76px_minmax(0,1fr)] sm:gap-7"
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
                        <p class="min-w-0 text-base leading-8 text-foreground/90">{{ segment.text }}</p>
                    </div>
                </div>
            </section>
        </div>
    </article>
</template>
