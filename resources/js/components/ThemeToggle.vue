<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const storageKey = 'transcriptions-theme';
const options = [
    { value: 'system', label: 'Sistema' },
    { value: 'light', label: 'Claro' },
    { value: 'dark', label: 'Escuro' },
];

const root = ref(null);
const isOpen = ref(false);
const preference = ref('system');
let mediaQuery;

const currentLabel = computed(() => options.find((option) => option.value === preference.value)?.label ?? 'Sistema');

const applyTheme = (value, persist = true) => {
    if (!options.some((option) => option.value === value)) {
        return;
    }

    preference.value = value;

    const systemIsDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const isDark = value === 'dark' || (value === 'system' && systemIsDark);
    const documentRoot = document.documentElement;

    documentRoot.classList.toggle('dark', isDark);
    documentRoot.dataset.theme = value;
    documentRoot.style.colorScheme = isDark ? 'dark' : 'light';

    if (persist) {
        try {
            localStorage.setItem(storageKey, value);
        } catch {
            // The selected theme still applies for the current page view.
        }
    }
};

const selectTheme = (value) => {
    applyTheme(value);
    isOpen.value = false;
};

const handleSystemChange = () => {
    if (preference.value === 'system') {
        applyTheme('system', false);
    }
};

const handleDocumentClick = (event) => {
    if (root.value && !root.value.contains(event.target)) {
        isOpen.value = false;
    }
};

const handleEscape = (event) => {
    if (event.key === 'Escape') {
        isOpen.value = false;
    }
};

onMounted(() => {
    preference.value = document.documentElement.dataset.theme ?? 'system';
    mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    mediaQuery.addEventListener('change', handleSystemChange);
    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('keydown', handleEscape);
});

onBeforeUnmount(() => {
    mediaQuery?.removeEventListener('change', handleSystemChange);
    document.removeEventListener('click', handleDocumentClick);
    document.removeEventListener('keydown', handleEscape);
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="inline-flex h-10 items-center gap-2 border border-border bg-card px-3 text-xs font-medium text-foreground transition-colors hover:bg-muted"
            aria-haspopup="menu"
            :aria-expanded="isOpen"
            aria-label="Selecionar tema da interface"
            @click="isOpen = !isOpen"
        >
            <svg v-if="preference === 'light'" viewBox="0 0 20 20" class="size-4" fill="none" aria-hidden="true">
                <circle cx="10" cy="10" r="3.25" stroke="currentColor" stroke-width="1.5" />
                <path d="M10 2v2M10 16v2M2 10h2M16 10h2M4.35 4.35l1.4 1.4M14.25 14.25l1.4 1.4M15.65 4.35l-1.4 1.4M5.75 14.25l-1.4 1.4" stroke="currentColor" stroke-width="1.5" />
            </svg>
            <svg v-else-if="preference === 'dark'" viewBox="0 0 20 20" class="size-4" fill="none" aria-hidden="true">
                <path d="M16.5 12.2A6.8 6.8 0 0 1 7.8 3.5 6.8 6.8 0 1 0 16.5 12.2Z" stroke="currentColor" stroke-width="1.5" />
            </svg>
            <svg v-else viewBox="0 0 20 20" class="size-4" fill="none" aria-hidden="true">
                <rect x="2.5" y="3.5" width="15" height="10" stroke="currentColor" stroke-width="1.5" />
                <path d="M7 17h6M10 13.5V17" stroke="currentColor" stroke-width="1.5" />
            </svg>
            <span class="hidden sm:inline">{{ currentLabel }}</span>
            <svg viewBox="0 0 16 16" class="size-3" fill="none" aria-hidden="true">
                <path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.5" />
            </svg>
        </button>

        <div
            v-if="isOpen"
            role="menu"
            aria-label="Preferência de tema"
            class="absolute right-0 z-50 mt-2 w-36 border border-border bg-card p-1 shadow-[0_12px_32px_rgba(0,0,0,0.12)]"
        >
            <button
                v-for="option in options"
                :key="option.value"
                type="button"
                role="menuitemradio"
                :aria-checked="preference === option.value"
                class="flex w-full items-center justify-between px-3 py-2 text-left text-sm text-foreground hover:bg-muted"
                @click="selectTheme(option.value)"
            >
                {{ option.label }}
                <span v-if="preference === option.value" class="text-accent" aria-hidden="true">●</span>
            </button>
        </div>
    </div>
</template>
