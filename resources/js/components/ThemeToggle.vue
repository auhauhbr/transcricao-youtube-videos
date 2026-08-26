<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const storageKey = 'transcriptions-theme';
const options = [
    { value: 'system', label: 'Sistema', icon: 'bi-display' },
    { value: 'light', label: 'Claro', icon: 'bi-sun' },
    { value: 'dark', label: 'Escuro', icon: 'bi-moon-stars' },
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
            class="ui-button-secondary px-3 text-xs"
            aria-haspopup="menu"
            :aria-expanded="isOpen"
            aria-label="Selecionar tema da interface"
            @click="isOpen = !isOpen"
        >
            <i :class="['bi', options.find((option) => option.value === preference)?.icon]" aria-hidden="true"></i>
            <span class="hidden sm:inline">{{ currentLabel }}</span>
            <i class="bi bi-chevron-down text-[10px]" aria-hidden="true"></i>
        </button>

        <div
            v-if="isOpen"
            role="menu"
            aria-label="Preferência de tema"
            class="absolute right-0 z-50 mt-1.5 w-36 border border-border bg-card p-1 shadow-[var(--shadow)]"
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
                <span><i :class="['bi', option.icon, 'mr-2 text-muted-foreground']" aria-hidden="true"></i>{{ option.label }}</span>
                <i v-if="preference === option.value" class="bi bi-check text-accent" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</template>
