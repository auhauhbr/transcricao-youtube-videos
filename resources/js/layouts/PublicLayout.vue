<script setup>
import PublicFooter from '../components/PublicFooter.vue';
import PublicHeader from '../components/PublicHeader.vue';

defineProps({
    appName: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <a
            href="#main-content"
            class="fixed left-4 top-4 z-[60] -translate-y-24 bg-foreground px-4 py-2 text-sm font-medium text-background focus:translate-y-0"
        >
            Ir para o conteúdo
        </a>
        <PublicHeader :app-name="appName" />
        <main id="main-content" tabindex="-1">
            <slot />
        </main>
        <PublicFooter :app-name="appName" />
    </div>
</template>
