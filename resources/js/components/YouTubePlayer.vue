<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { loadYouTubeIframeApi } from '../utils/youtubeIframeApi.js';

const props = defineProps({
    videoId: {
        type: String,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['error', 'ready', 'state-change', 'time-update']);
const playerTarget = ref(null);
const initializationFailed = ref(false);
const embedUrl = computed(() => {
    const parameters = new URLSearchParams({
        enablejsapi: '1',
        playsinline: '1',
        rel: '0',
    });

    if (typeof window !== 'undefined') {
        parameters.set('origin', window.location.origin);
    }

    return `https://www.youtube-nocookie.com/embed/${encodeURIComponent(props.videoId)}?${parameters.toString()}`;
});

let player = null;
let playerReady = false;
let pendingSeek = null;
let timeTimer = null;
let unmounted = false;

const emitCurrentTime = () => {
    if (!playerReady || typeof player?.getCurrentTime !== 'function') {
        return;
    }

    const currentTime = Number(player.getCurrentTime());

    if (Number.isFinite(currentTime)) {
        emit('time-update', currentTime);
    }
};

const startTimeUpdates = () => {
    if (timeTimer !== null) {
        return;
    }

    timeTimer = window.setInterval(emitCurrentTime, 400);
};

const stopTimeUpdates = () => {
    if (timeTimer !== null) {
        window.clearInterval(timeTimer);
        timeTimer = null;
    }
};

const handlePlayerError = () => {
    playerReady = false;
    initializationFailed.value = true;
    stopTimeUpdates();
    emit('error');
};

const seekTo = (seconds, shouldPlay = true) => {
    const safeSeconds = Number(seconds);

    if (!Number.isFinite(safeSeconds) || safeSeconds < 0 || initializationFailed.value) {
        return false;
    }

    if (!playerReady) {
        pendingSeek = { seconds: safeSeconds, shouldPlay };
        return false;
    }

    player.seekTo(safeSeconds, true);

    if (shouldPlay && typeof player.playVideo === 'function') {
        player.playVideo();
    }

    emit('time-update', safeSeconds);

    return true;
};

const getCurrentTime = () => (playerReady ? Number(player.getCurrentTime()) : null);
const getPlayerState = () => (playerReady ? player.getPlayerState() : null);

defineExpose({ getCurrentTime, getPlayerState, seekTo });

onMounted(async () => {
    try {
        const YT = await loadYouTubeIframeApi();

        if (unmounted || !playerTarget.value) {
            return;
        }

        player = new YT.Player(playerTarget.value, {
            events: {
                onReady: () => {
                    if (unmounted) {
                        return;
                    }

                    playerReady = true;
                    startTimeUpdates();
                    emit('ready');
                    emitCurrentTime();

                    if (pendingSeek) {
                        const seek = pendingSeek;
                        pendingSeek = null;
                        seekTo(seek.seconds, seek.shouldPlay);
                    }
                },
                onStateChange: (event) => {
                    emit('state-change', event.data);
                    emitCurrentTime();
                },
                onError: handlePlayerError,
            },
        });
    } catch {
        handlePlayerError();
    }
});

onBeforeUnmount(() => {
    unmounted = true;
    playerReady = false;
    pendingSeek = null;
    stopTimeUpdates();

    if (typeof player?.destroy === 'function') {
        player.destroy();
    }

    player = null;
});
</script>

<template>
    <div>
        <div class="aspect-video overflow-hidden bg-black">
            <iframe
                ref="playerTarget"
                :src="embedUrl"
                :title="`Player do vídeo ${title}`"
                class="size-full border-0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                referrerpolicy="strict-origin-when-cross-origin"
                allowfullscreen
            ></iframe>
        </div>
        <p v-if="initializationFailed" class="border-t border-white/15 bg-black px-4 py-2.5 text-xs leading-5 text-white/75" role="status">
            A sincronização do player está indisponível. Use os controles do vídeo ou o link abaixo.
        </p>
    </div>
</template>
