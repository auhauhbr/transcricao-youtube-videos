<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppWordmark from './AppWordmark.vue';
import ThemeToggle from './ThemeToggle.vue';

defineProps({
    appName: {
        type: String,
        required: true,
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-border bg-background">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-5 sm:px-8 lg:px-10">
            <AppWordmark :app-name="appName" />
            <div class="flex items-center gap-2 sm:gap-3">
                <template v-if="user">
                    <Link
                        href="/account"
                        class="inline-flex h-10 items-center border border-border bg-card px-3 text-xs font-semibold text-foreground transition-colors hover:border-accent hover:text-accent sm:px-4 sm:text-sm"
                    >
                        <span class="max-w-24 truncate sm:max-w-40">{{ user.name }}</span>
                    </Link>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="inline-flex h-10 items-center px-2 text-xs font-semibold text-muted-foreground transition-colors hover:text-accent sm:px-3 sm:text-sm"
                    >
                        Sair
                    </Link>
                </template>
                <template v-else>
                    <Link href="/login" class="hidden text-sm font-semibold text-muted-foreground transition-colors hover:text-accent sm:inline-flex">
                        Entrar
                    </Link>
                    <Link
                        href="/register"
                        class="inline-flex h-10 items-center border border-border bg-card px-3 text-xs font-semibold text-foreground transition-colors hover:border-accent hover:text-accent sm:px-4 sm:text-sm"
                    >
                        Criar conta
                    </Link>
                </template>
                <ThemeToggle />
            </div>
        </div>
    </header>
</template>
