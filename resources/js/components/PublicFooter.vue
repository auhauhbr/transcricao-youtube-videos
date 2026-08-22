<script setup>
import AppWordmark from './AppWordmark.vue';

defineProps({
    appName: {
        type: String,
        required: true,
    },
});

const currentYear = new Date().getFullYear();
</script>

<template>
    <footer class="border-t border-border bg-card">
        <div class="mx-auto flex max-w-7xl flex-col gap-5 px-5 py-8 sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-10">
            <AppWordmark :app-name="appName" />
            <p class="text-xs text-muted-foreground">© {{ currentYear }} {{ appName }}.</p>
        </div>
    </footer>
</template>
