<script setup>
import { computed } from 'vue';
import { formatTimestamp } from '../utils/formatTimestamp.js';

const props = defineProps({
    video: {
        type: Object,
        required: true,
    },
});

const durationLabel = computed(() => formatTimestamp(props.video.durationSeconds * 1000));
</script>

<template>
    <section class="border border-border bg-card" aria-labelledby="video-summary-title">
        <a
            :href="video.youtubeUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="group relative block aspect-video overflow-hidden border-b border-border bg-muted"
            aria-label="Abrir vídeo no YouTube"
        >
            <img
                v-if="video.thumbnailUrl"
                :src="video.thumbnailUrl"
                :alt="`Thumbnail do vídeo ${video.title}`"
                class="size-full object-cover transition-transform duration-300 group-hover:scale-[1.02]"
            />
            <div v-else class="flex size-full items-center justify-center text-muted-foreground" aria-hidden="true">
                <svg class="size-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M4 6.5h16v11H4z" />
                    <path d="m10 9 5 3-5 3V9Z" />
                </svg>
            </div>
            <span
                class="absolute left-1/2 top-1/2 flex size-12 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-black/75 text-white shadow-lg transition-colors group-hover:bg-accent"
                aria-hidden="true"
            >
                <svg class="ml-0.5 size-5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="m9 7 8 5-8 5V7Z" />
                </svg>
            </span>
            <span class="absolute bottom-2 right-2 bg-black/80 px-2 py-1 font-mono text-[11px] font-semibold text-white">
                {{ durationLabel }}
            </span>
        </a>

        <div class="p-5">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-accent">Vídeo do YouTube</p>
            <h2 id="video-summary-title" class="mt-2 text-lg font-semibold leading-6 tracking-tight text-foreground">
                {{ video.title }}
            </h2>
            <p class="mt-2 text-sm text-muted-foreground">{{ video.channelName }}</p>

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
                class="mt-5 inline-flex h-10 w-full items-center justify-center border border-border bg-background px-4 text-sm font-semibold text-foreground transition-colors hover:border-accent hover:text-accent"
            >
                Abrir no YouTube
                <span class="ml-2" aria-hidden="true">↗</span>
            </a>
        </div>
    </section>
</template>
