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
    <Link href="/" class="inline-flex min-w-0 items-center gap-2.5 text-foreground" aria-label="Ir para a página inicial">
        <img :src="brandIconUrl" alt="" width="28" height="28" class="size-7 shrink-0 object-contain" />
        <span class="truncate text-[15px] font-semibold tracking-[-0.01em]">{{ appName }}</span>
    </Link>
</template>
