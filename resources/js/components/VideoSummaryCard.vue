<script setup>
import { computed, ref } from 'vue';
import { formatTimestamp } from '../utils/formatTimestamp.js';
import YouTubePlayer from './YouTubePlayer.vue';

const props = defineProps({
    video: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['player-error', 'player-ready', 'state-change', 'time-update']);
const player = ref(null);

const seekTo = (seconds, shouldPlay = true) => player.value?.seekTo(seconds, shouldPlay) ?? false;

defineExpose({ seekTo });

const durationLabel = computed(() => formatTimestamp(props.video.durationSeconds * 1000));
</script>

<template>
    <section class="ui-panel" aria-labelledby="video-summary-title">
        <div class="border-b border-border">
            <YouTubePlayer
                ref="player"
                :video-id="video.providerVideoId"
                :title="video.title"
                @error="emit('player-error')"
                @ready="emit('player-ready')"
                @state-change="emit('state-change', $event)"
                @time-update="emit('time-update', $event)"
            />
        </div>

        <div class="p-5">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-accent">Vídeo do YouTube</p>
            <h2 id="video-summary-title" class="mt-2 text-lg font-semibold leading-6 tracking-tight text-foreground">
                {{ video.title }}
            </h2>
            <p v-if="video.channelName" class="mt-2 text-sm text-muted-foreground">{{ video.channelName }}</p>

            <dl class="mt-5 divide-y divide-border border-y border-border text-xs">
                <div class="flex items-start justify-between gap-4 py-3">
                    <dt class="text-muted-foreground">Duração</dt>
                    <dd class="font-medium text-foreground">{{ durationLabel }}</dd>
                </div>
                <div v-if="video.channelId" class="flex items-start justify-between gap-4 py-3">
                    <dt class="shrink-0 text-muted-foreground">Channel ID</dt>
                    <dd class="min-w-0 break-all text-right font-mono text-foreground">{{ video.channelId }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4 py-3">
                    <dt class="shrink-0 text-muted-foreground">Video ID</dt>
                    <dd class="min-w-0 break-all text-right font-mono text-foreground">{{ video.providerVideoId }}</dd>
                </div>
            </dl>

            <a
                :href="video.youtubeUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="ui-button-secondary mt-5 w-full"
            >
                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Abrir no YouTube
            </a>
        </div>
    </section>
</template>
