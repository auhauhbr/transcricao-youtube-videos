<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { formatTimestamp } from '../utils/formatTimestamp.js';
import { useTranscriptAnnotations } from '../composables/useTranscriptAnnotations.js';
import AnnotationControls from './AnnotationControls.vue';
import AnnotationEditorDialog from './AnnotationEditorDialog.vue';
import AnnotationsPanel from './AnnotationsPanel.vue';
import FlashToast from './FlashToast.vue';
import VideoSummaryCard from './VideoSummaryCard.vue';

const props = defineProps({ source: { type: Object, required: true }, annotations: { type: Object, required: true } });
const videoCard = ref(null);
const transcriptPanel = ref(null);
const currentTimeMs = ref(null);
const autoscrollEnabled = ref(true);
const blockElements = new Map();
const annotationsPanelOpen = ref(false);
const annotationEditor = ref(null);
const annotationFeedback = ref(null);
const annotationFeedbackId = ref(0);
const annotationState = useTranscriptAnnotations(props.annotations.items, props.annotations.urls);
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
const openNoteEditor = (startMs) => {
    const note = annotationState.noteAt(startMs);
    annotationEditor.value = { startMs, annotation: note, text: note?.text || '' };
};
const saveNote = async (text) => {
    if (!annotationEditor.value) return;
    const result = await annotationState.saveNote(annotationEditor.value.startMs, text, annotationEditor.value.annotation);
    if (result) {
        annotationEditor.value = null;
        annotationFeedback.value = 'Nota salva.';
        annotationFeedbackId.value += 1;
    }
};
const toggleBookmark = async (startMs) => {
    const hadBookmark = Boolean(annotationState.bookmarkAt(startMs));
    const result = await annotationState.toggleBookmark(startMs);
    if (result) {
        annotationFeedback.value = hadBookmark ? 'Marcador removido.' : 'Marcador adicionado.';
        annotationFeedbackId.value += 1;
    }
};
const removeAnnotation = async (annotation) => {
    const result = await annotationState.remove(annotation);
    if (result) {
        annotationFeedback.value = annotation.type === 'note' ? 'Nota excluída.' : 'Marcador removido.';
        annotationFeedbackId.value += 1;
    }
};
const navigateToAnnotation = async (startMs) => {
    const block = props.source.transcript.blocks.find((item) => item.startMs === startMs);
    if (!block) return;
    seekTo(startMs);
    await nextTick();
    blockElements.get(block.position)?.scrollIntoView({ block: 'center', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    annotationsPanelOpen.value = false;
};

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
            <div class="flex items-center gap-2"><button type="button" class="ui-button-secondary min-h-9 px-3 text-xs" aria-label="Abrir notas e marcadores" @click="annotationsPanelOpen = true"><i class="bi bi-journal-bookmark" aria-hidden="true"></i> Notas</button><button type="button" class="ui-button-secondary min-h-9 px-3 text-xs" :aria-pressed="autoscrollEnabled" @click="autoscrollEnabled = !autoscrollEnabled"><i :class="['bi', autoscrollEnabled ? 'bi-arrow-down-circle-fill' : 'bi-arrow-down-circle']" aria-hidden="true"></i> Autoscroll</button></div>
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
                <div
                    v-for="block in source.transcript.blocks"
                    :key="block.position"
                    :ref="(element) => setBlockElement(block.position, element)"
                    role="button"
                    tabindex="0"
                    class="grid w-full grid-cols-[58px_minmax(0,1fr)_auto] gap-3 border-l-2 border-transparent px-4 py-3 text-left text-sm leading-6 hover:bg-muted/70"
                    :class="activeBlockPosition === block.position ? 'border-l-accent bg-accent/[0.07]' : ''"
                    :aria-current="activeBlockPosition === block.position ? 'true' : undefined"
                    :aria-label="`Reproduzir a partir de ${formatTimestamp(block.startMs)}: ${block.text}`"
                    @click="seekTo(block.startMs)"
                    @keydown.enter.prevent="seekTo(block.startMs)"
                    @keydown.space.prevent="seekTo(block.startMs)"
                >
                    <span class="font-mono text-xs font-semibold text-accent">{{ formatTimestamp(block.startMs) }}</span><span>{{ block.text }}</span>
                    <AnnotationControls :start-ms="block.startMs" :bookmarked="Boolean(annotationState.bookmarkAt(block.startMs))" :has-note="Boolean(annotationState.noteAt(block.startMs))" :disabled="annotationState.busy" @toggle-bookmark="toggleBookmark" @edit-note="openNoteEditor" />
                </div>
            </div>
        </div>
        <p v-if="annotationState.error" class="mt-3 text-sm text-destructive" role="alert">{{ annotationState.error }}</p>
        <AnnotationsPanel :open="annotationsPanelOpen" :items="annotationState.items" :busy="annotationState.busy" @close="annotationsPanelOpen = false" @navigate="navigateToAnnotation" @edit-note="openNoteEditor" @delete="removeAnnotation" @toggle-bookmark="toggleBookmark" />
        <AnnotationEditorDialog :open="Boolean(annotationEditor)" :start-ms="annotationEditor?.startMs" :text="annotationEditor?.text || ''" :busy="annotationState.busy" @cancel="annotationEditor = null" @save="saveNote" />
        <FlashToast :flash-id="String(annotationFeedbackId)" :message="annotationFeedback" />
    </section>
</template>
