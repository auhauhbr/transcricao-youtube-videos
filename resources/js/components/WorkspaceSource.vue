<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { formatTimestamp } from '../utils/formatTimestamp.js';
import VideoSummaryCard from './VideoSummaryCard.vue';

const props = defineProps({ source: { type: Object, required: true } });
const videoCard = ref(null);
const transcriptPanel = ref(null);
const currentTimeMs = ref(null);
const autoscrollEnabled = ref(true);
const blockElements = new Map();
const activeBlockPosition = computed(() => {
    if (!Number.isFinite(currentTimeMs.value)) return null;
    const active = [...props.source.transcript.blocks].reverse().find((block) => block.startMs <= currentTimeMs.value);
    return active && currentTimeMs.value < active.endMs ? active.position : null;
});
const setBlockElement = (position, element) => element ? blockElements.set(position, element) : blockElements.delete(position);
const seekTo = (startMs) => {
    currentTimeMs.value = startMs;
    videoCard.value?.seekTo(startMs / 1000, true);
};
const updateCurrentTime = (seconds) => { if (Number.isFinite(Number(seconds))) currentTimeMs.value = Number(seconds) * 1000; };

watch(activeBlockPosition, async (position) => {
    if (!autoscrollEnabled.value || position === null) return;
    await nextTick();
    blockElements.get(position)?.scrollIntoView({ block: 'nearest', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
});
</script>

<template>
    <section class="min-w-0" aria-labelledby="workspace-source-title">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div><p class="ui-eyebrow">Fonte imutável</p><h2 id="workspace-source-title" class="mt-1 text-xl font-semibold">Transcrição original</h2></div>
            <button type="button" class="ui-button-secondary min-h-9 px-3 text-xs" :aria-pressed="autoscrollEnabled" @click="autoscrollEnabled = !autoscrollEnabled">
                <i :class="['bi', autoscrollEnabled ? 'bi-arrow-down-circle-fill' : 'bi-arrow-down-circle']" aria-hidden="true"></i> Autoscroll
            </button>
        </div>
        <VideoSummaryCard ref="videoCard" :video="source.video" @time-update="updateCurrentTime" />
        <nav v-if="source.transcript.chapters.length" class="ui-panel mt-4 p-4" aria-label="Capítulos da fonte">
            <h3 class="text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground">Capítulos</h3>
            <ol class="mt-2 divide-y divide-border">
                <li v-for="chapter in source.transcript.chapters" :key="chapter.position">
                    <button type="button" class="grid w-full grid-cols-[50px_minmax(0,1fr)] gap-2 py-2.5 text-left text-sm" :aria-label="`Reproduzir ${chapter.title} em ${formatTimestamp(chapter.startMs)}`" @click="seekTo(chapter.startMs)">
                        <span class="font-mono text-xs font-semibold text-accent">{{ formatTimestamp(chapter.startMs) }}</span><span>{{ chapter.title }}</span>
                    </button>
                </li>
            </ol>
        </nav>
        <div class="ui-panel mt-4 min-w-0">
            <div class="border-b border-border p-4 text-xs text-muted-foreground">
                {{ source.transcript.languageName || source.transcript.languageCode }} · {{ source.transcript.sourceLabel }}
            </div>
            <div ref="transcriptPanel" class="divide-y divide-border lg:max-h-[52vh] lg:overflow-y-auto">
                <button
                    v-for="block in source.transcript.blocks"
                    :key="block.position"
                    :ref="(element) => setBlockElement(block.position, element)"
                    type="button"
                    class="grid w-full grid-cols-[58px_minmax(0,1fr)] gap-3 border-l-2 border-transparent px-4 py-3 text-left text-sm leading-6 hover:bg-muted/70"
                    :class="activeBlockPosition === block.position ? 'border-l-accent bg-accent/[0.07]' : ''"
                    :aria-current="activeBlockPosition === block.position ? 'true' : undefined"
                    :aria-label="`Reproduzir a partir de ${formatTimestamp(block.startMs)}: ${block.text}`"
                    @click="seekTo(block.startMs)"
                >
                    <span class="font-mono text-xs font-semibold text-accent">{{ formatTimestamp(block.startMs) }}</span><span>{{ block.text }}</span>
                </button>
            </div>
        </div>
    </section>
</template>
