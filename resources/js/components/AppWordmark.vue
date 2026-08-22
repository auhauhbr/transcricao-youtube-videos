<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    appName: {
        type: String,
        required: true,
    },
});

const brandIconUrl = '/favicon.png';
</script>

<template>
    <Link href="/" class="inline-flex min-w-0 items-center gap-3 text-foreground" aria-label="Ir para a página inicial">
        <img :src="brandIconUrl" alt="" width="32" height="32" class="size-8 shrink-0 object-contain" />
        <span class="truncate text-sm font-semibold tracking-tight sm:text-base">{{ appName }}</span>
    </Link>
</template>
