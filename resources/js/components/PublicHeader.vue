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
    <header class="sticky top-0 z-40 border-b border-border bg-header">
        <div class="mx-auto flex h-14 max-w-7xl items-center justify-between gap-3 px-4 sm:h-[3.75rem] sm:px-8 lg:px-10">
            <AppWordmark :app-name="appName" />
            <div class="flex items-center gap-2 sm:gap-3">
                <template v-if="user">
                    <nav class="hidden items-center gap-1 sm:flex" aria-label="Navegação da conta">
                        <Link href="/library" class="ui-button-ghost">
                            <i class="bi bi-collection" aria-hidden="true"></i>
                            Biblioteca
                        </Link>
                        <Link
                            href="/account"
                            class="ui-button-secondary"
                        >
                            <i class="bi bi-person" aria-hidden="true"></i>
                            <span class="max-w-36 truncate">{{ user.name }}</span>
                        </Link>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="ui-button-ghost"
                        >
                            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                            Sair
                        </Link>
                    </nav>
                    <div ref="mobileMenu" class="relative sm:hidden">
                        <button
                            ref="mobileMenuButton"
                            type="button"
                            class="ui-button-secondary px-3"
                            aria-haspopup="menu"
                            :aria-expanded="mobileMenuOpen"
                            @click="mobileMenuOpen = !mobileMenuOpen"
                        >
                            <i class="bi bi-list text-base" aria-hidden="true"></i>
                            <span class="sr-only">Menu</span>
                        </button>
                        <div
                            v-if="mobileMenuOpen"
                            role="menu"
                            aria-label="Navegação da conta"
                            class="absolute right-0 z-50 mt-1.5 w-48 border border-border bg-card p-1 shadow-[var(--shadow)]"
                        >
                            <Link href="/library" role="menuitem" class="flex min-h-11 items-center px-3 text-sm font-semibold text-foreground hover:bg-muted" @click="closeMobileMenu">
                                <i class="bi bi-collection mr-2" aria-hidden="true"></i> Biblioteca
                            </Link>
                            <Link href="/account" role="menuitem" class="flex min-h-11 items-center px-3 text-sm font-semibold text-foreground hover:bg-muted" @click="closeMobileMenu">
                                <i class="bi bi-person mr-2" aria-hidden="true"></i> Minha conta
                            </Link>
                            <Link href="/logout" method="post" as="button" role="menuitem" class="flex min-h-11 w-full items-center px-3 text-left text-sm font-semibold text-foreground hover:bg-muted" @click="closeMobileMenu">
                                <i class="bi bi-box-arrow-right mr-2" aria-hidden="true"></i> Sair
                            </Link>
                        </div>
                    </div>
                </template>
                <template v-else>
                    <Link href="/login" class="ui-button-ghost hidden sm:inline-flex">
                        Entrar
                    </Link>
                    <Link
                        href="/register"
                        class="ui-button-secondary px-3 text-xs sm:px-4 sm:text-sm"
                    >
                        Criar conta
                    </Link>
                </template>
                <ThemeToggle />
            </div>
        </div>
    </header>
</template>
