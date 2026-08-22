<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
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
const mobileMenu = ref(null);
const mobileMenuButton = ref(null);
const mobileMenuOpen = ref(false);

const closeMobileMenu = () => {
    mobileMenuOpen.value = false;
};

const handleDocumentClick = (event) => {
    if (mobileMenu.value && !mobileMenu.value.contains(event.target)) {
        closeMobileMenu();
    }
};

const handleEscape = (event) => {
    if (event.key === 'Escape' && mobileMenuOpen.value) {
        closeMobileMenu();
        nextTick(() => mobileMenuButton.value?.focus());
    }
};

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('keydown', handleEscape);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
    document.removeEventListener('keydown', handleEscape);
});
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-border bg-background">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-5 sm:px-8 lg:px-10">
            <AppWordmark :app-name="appName" />
            <div class="flex items-center gap-2 sm:gap-3">
                <template v-if="user">
                    <nav class="hidden items-center gap-1 sm:flex" aria-label="Navegação da conta">
                        <Link href="/library" class="inline-flex h-10 items-center px-3 text-sm font-semibold text-muted-foreground transition-colors hover:text-accent">
                            Biblioteca
                        </Link>
                        <Link
                            href="/account"
                            class="inline-flex h-10 items-center border border-border bg-card px-4 text-sm font-semibold text-foreground transition-colors hover:border-accent hover:text-accent"
                        >
                            <span class="max-w-36 truncate">{{ user.name }}</span>
                        </Link>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="inline-flex h-10 items-center px-3 text-sm font-semibold text-muted-foreground transition-colors hover:text-accent"
                        >
                            Sair
                        </Link>
                    </nav>
                    <div ref="mobileMenu" class="relative sm:hidden">
                        <button
                            ref="mobileMenuButton"
                            type="button"
                            class="inline-flex h-10 items-center border border-border bg-card px-3 text-xs font-semibold text-foreground"
                            aria-haspopup="menu"
                            :aria-expanded="mobileMenuOpen"
                            @click="mobileMenuOpen = !mobileMenuOpen"
                        >
                            Menu
                        </button>
                        <div
                            v-if="mobileMenuOpen"
                            role="menu"
                            aria-label="Navegação da conta"
                            class="absolute right-0 z-50 mt-2 w-44 border border-border bg-card p-1 shadow-[0_12px_32px_rgba(0,0,0,0.12)]"
                        >
                            <Link href="/library" role="menuitem" class="flex min-h-11 items-center px-3 text-sm font-semibold text-foreground hover:bg-muted" @click="closeMobileMenu">
                                Biblioteca
                            </Link>
                            <Link href="/account" role="menuitem" class="flex min-h-11 items-center px-3 text-sm font-semibold text-foreground hover:bg-muted" @click="closeMobileMenu">
                                Minha conta
                            </Link>
                            <Link href="/logout" method="post" as="button" role="menuitem" class="flex min-h-11 w-full items-center px-3 text-left text-sm font-semibold text-foreground hover:bg-muted" @click="closeMobileMenu">
                                Sair
                            </Link>
                        </div>
                    </div>
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
