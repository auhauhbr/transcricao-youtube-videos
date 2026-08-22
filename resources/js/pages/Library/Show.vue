<script setup>
import { Head } from '@inertiajs/vue3';
import TranscriptResult from '../../components/TranscriptResult.vue';
import PublicLayout from '../../layouts/PublicLayout.vue';

defineProps({
    appName: { type: String, required: true },
    video: { type: Object, required: true },
    transcript: { type: Object, required: true },
    downloadUrl: { type: String, required: true },
    backUrl: { type: String, required: true },
});
</script>

<template>
    <Head :title="video.title">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <PublicLayout :app-name="appName">
        <TranscriptResult
            :video="video"
            :transcript="transcript"
            :download-url="downloadUrl"
            :back-url="backUrl"
            back-label="Voltar à biblioteca"
        />
    </PublicLayout>
</template>
