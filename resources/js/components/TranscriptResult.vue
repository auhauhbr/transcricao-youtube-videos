<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { formatTimestamp } from '../utils/formatTimestamp.js';
import TranscriptDownloadDialog from './TranscriptDownloadDialog.vue';
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
    downloadUrl: {
        type: String,
        required: true,
    },
    backUrl: {
        type: String,
        default: '/',
    },
    backLabel: {
        type: String,
        default: 'Voltar',
    },
    libraryUrl: {
        type: String,
        default: null,
    },
});

const copyMessage = ref('');
const copyFailed = ref(false);
const autoscrollEnabled = ref(false);
const currentTimeMs = ref(null);
const transcriptPanel = ref(null);
const videoCard = ref(null);
const blockElements = new Map();
let scrollCancellationFrame = null;
const fullTranscript = computed(() => props.transcript.blocks.map((block) => block.text).join('\n\n'));
const languageLabel = computed(() => props.transcript.languageName || props.transcript.languageCode);
const sourceLabel = computed(() => props.transcript.sourceLabel || (props.transcript.source === 'manual' ? 'Legendas manuais' : 'Legendas automáticas'));
const activeBlockPosition = computed(() => {
    const time = currentTimeMs.value;
    const blocks = props.transcript.blocks;

    if (!Number.isFinite(time) || blocks.length === 0) {
        return null;
    }

    let low = 0;
    let high = blocks.length - 1;
    let candidate = null;

    while (low <= high) {
        const middle = Math.floor((low + high) / 2);

        if (blocks[middle].startMs <= time) {
            candidate = blocks[middle];
            low = middle + 1;
        } else {
            high = middle - 1;
        }
    }

    return candidate && time < candidate.endMs ? candidate.position : null;
});

const setBlockElement = (position, element) => {
    if (element) {
        blockElements.set(position, element);
        return;
    }

    blockElements.delete(position);
};

const panelCanScroll = (panel) => {
    const overflow = window.getComputedStyle(panel).overflowY;

    return panel.scrollHeight > panel.clientHeight && ['auto', 'scroll'].includes(overflow);
};

const cancelAutoscrollMotion = () => {
    const panel = transcriptPanel.value;
    const usePanel = panel && panelCanScroll(panel);
    const lockedPosition = usePanel ? panel.scrollTop : window.scrollY;
    const stopAtCurrentPosition = () => {
        if (usePanel && panel) {
            panel.scrollTo({ top: lockedPosition, behavior: 'instant' });
        } else {
            window.scrollTo({ top: lockedPosition, behavior: 'instant' });
        }
    };

    stopAtCurrentPosition();

    if (scrollCancellationFrame !== null) {
        window.cancelAnimationFrame(scrollCancellationFrame);
    }

    scrollCancellationFrame = window.requestAnimationFrame(() => {
        if (!autoscrollEnabled.value) {
            stopAtCurrentPosition();
        }

        scrollCancellationFrame = null;
    });
};

const scrollToBlock = async (position) => {
    await nextTick();

    const panel = transcriptPanel.value;
    const element = blockElements.get(position);

    if (!panel || !element) {
        return;
    }

    const panelBounds = panel.getBoundingClientRect();
    const elementBounds = element.getBoundingClientRect();
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!panelCanScroll(panel)) {
        element.scrollIntoView({
            behavior: reduceMotion ? 'auto' : 'smooth',
            block: 'center',
        });

        return;
    }

    if (elementBounds.top >= panelBounds.top && elementBounds.bottom <= panelBounds.bottom) {
        return;
    }

    panel.scrollTo({
        top: panel.scrollTop + elementBounds.top - panelBounds.top - panel.clientHeight / 2 + elementBounds.height / 2,
        behavior: reduceMotion ? 'auto' : 'smooth',
    });
};

const seekTo = (startMs) => {
    const safeStartMs = Math.max(0, Number(startMs) || 0);
    currentTimeMs.value = safeStartMs;
    videoCard.value?.seekTo(safeStartMs / 1000, true);
};

const updateCurrentTime = (seconds) => {
    const numericSeconds = Number(seconds);

    if (Number.isFinite(numericSeconds)) {
        currentTimeMs.value = numericSeconds * 1000;
    }
};

const toggleAutoscroll = () => {
    autoscrollEnabled.value = !autoscrollEnabled.value;

    if (autoscrollEnabled.value && activeBlockPosition.value !== null) {
        scrollToBlock(activeBlockPosition.value);
        return;
    }

    if (!autoscrollEnabled.value && transcriptPanel.value) {
        cancelAutoscrollMotion();
    }
};

watch(activeBlockPosition, (position, previousPosition) => {
    if (autoscrollEnabled.value && position !== null && position !== previousPosition) {
        scrollToBlock(position);
    }
});

onBeforeUnmount(() => {
    if (scrollCancellationFrame !== null) {
        window.cancelAnimationFrame(scrollCancellationFrame);
    }
});

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
        <div class="mx-auto max-w-7xl px-5 py-7 sm:px-8 sm:py-9 lg:px-10">
            <header class="border-b border-border pb-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <Link :href="backUrl" class="ui-button-ghost px-0">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i> {{ backLabel }}
                    </Link>
                    <Link
                        v-if="libraryUrl"
                        :href="libraryUrl"
                        class="ui-button-secondary"
                    >
                        Abrir na biblioteca
                    </Link>
                </div>
                <div class="mt-5 min-w-0 max-w-5xl">
                    <p class="ui-eyebrow">Transcrição pronta</p>
                    <h1 class="mt-2 text-2xl font-semibold leading-tight tracking-[-0.025em] text-foreground sm:text-3xl">
                        {{ video.title }}
                    </h1>
                    <p class="mt-3 text-sm text-muted-foreground sm:text-base">
                        <template v-if="video.channelName">{{ video.channelName }} · </template>{{ formatTimestamp(video.durationSeconds * 1000) }} · {{ languageLabel }}
                    </p>
                </div>
            </header>

            <div class="mt-6 grid items-start gap-5 lg:grid-cols-[300px_minmax(0,1fr)] lg:gap-6">
                <aside class="space-y-5 lg:sticky lg:top-24">
                    <VideoSummaryCard ref="videoCard" :video="video" @time-update="updateCurrentTime" />

                    <nav v-if="transcript.chapters.length" class="ui-panel p-4" aria-labelledby="chapters-title">
                        <div class="flex items-center justify-between gap-4">
                            <h2 id="chapters-title" class="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Capítulos</h2>
                            <span class="text-xs text-muted-foreground">{{ transcript.chapters.length }}</span>
                        </div>
                        <ol class="mt-3 divide-y divide-border">
                            <li v-for="chapter in transcript.chapters" :key="chapter.position">
                                <button
                                    type="button"
                                    class="grid w-full grid-cols-[48px_minmax(0,1fr)] gap-2 py-3 text-left text-sm text-muted-foreground transition-colors hover:text-foreground"
                                    :aria-label="`Reproduzir o capítulo ${chapter.title} em ${formatTimestamp(chapter.startMs)}`"
                                    @click="seekTo(chapter.startMs)"
                                >
                                    <span class="font-mono text-xs font-semibold text-accent">{{ formatTimestamp(chapter.startMs) }}</span>
                                    <span class="leading-5">{{ chapter.title }}</span>
                                </button>
                            </li>
                        </ol>
                    </nav>
                </aside>

                <section class="ui-panel min-w-0" aria-labelledby="transcript-title">
                    <div class="flex flex-col gap-4 border-b border-border p-4 sm:flex-row sm:items-start sm:justify-between sm:p-5">
                        <div>
                            <h2 id="transcript-title" class="text-xl font-semibold tracking-tight text-foreground">Transcrição completa</h2>
                            <p class="mt-1.5 text-xs leading-5 text-muted-foreground">
                                {{ languageLabel }} · {{ sourceLabel }} · {{ transcript.wordCount.toLocaleString('pt-BR') }} palavras
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="ui-button-secondary"
                                :aria-pressed="autoscrollEnabled"
                                @click="toggleAutoscroll"
                            >
                                <i :class="['bi', autoscrollEnabled ? 'bi-arrow-down-circle-fill' : 'bi-arrow-down-circle']" aria-hidden="true"></i>
                                Autoscroll {{ autoscrollEnabled ? 'ligado' : 'desligado' }}
                            </button>
                            <TranscriptDownloadDialog :download-url="downloadUrl" />
                            <button
                                type="button"
                                class="ui-button-primary"
                                @click="copyTranscript"
                            >
                                <i class="bi bi-copy" aria-hidden="true"></i> Copiar tudo
                            </button>
                        </div>
                    </div>

                    <p
                        v-if="copyMessage"
                        class="border-b border-border px-5 py-3 text-sm sm:px-6"
                        :class="copyFailed ? 'text-destructive' : 'text-muted-foreground'"
                        aria-live="polite"
                    >
                        {{ copyMessage }}
                    </p>

                    <div ref="transcriptPanel" class="divide-y divide-border lg:max-h-[calc(100vh-15rem)] lg:min-h-[30rem] lg:overflow-y-auto">
                        <button
                            v-for="block in transcript.blocks"
                            :id="`transcript-block-${block.position}`"
                            :key="block.position"
                            :ref="(element) => setBlockElement(block.position, element)"
                            type="button"
                            class="group grid w-full scroll-mt-24 grid-cols-[58px_minmax(0,1fr)] gap-3 border-l-2 border-transparent px-4 py-3.5 text-left transition-colors hover:bg-muted/70 sm:grid-cols-[70px_minmax(0,1fr)] sm:gap-5 sm:px-5 sm:py-4"
                            :class="activeBlockPosition === block.position ? 'border-l-accent bg-accent/[0.07]' : ''"
                            :aria-current="activeBlockPosition === block.position ? 'true' : undefined"
                            :aria-label="`Reproduzir a partir de ${formatTimestamp(block.startMs)}: ${block.text}`"
                            @click="seekTo(block.startMs)"
                        >
                            <span class="h-fit font-mono text-xs font-semibold text-accent group-hover:underline">
                                {{ formatTimestamp(block.startMs) }}
                            </span>
                            <span class="min-w-0 text-sm leading-6 text-foreground/90 sm:text-[15px] sm:leading-7">{{ block.text }}</span>
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </article>
</template>
