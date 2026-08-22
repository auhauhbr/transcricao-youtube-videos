<script setup>
import AppWordmark from './AppWordmark.vue';
import ThemeToggle from './ThemeToggle.vue';

defineProps({
    appName: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-border bg-background">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-5 sm:px-8 lg:px-10">
            <AppWordmark :app-name="appName" />
            <ThemeToggle />
        </div>
    </header>
</template>
