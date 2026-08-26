<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    flashId: { type: String, default: null },
    message: { type: String, default: null },
    duration: { type: Number, default: 2800 },
});

const visible = ref(false);
let dismissTimer = null;
let presentation = 0;

const clearDismissTimer = () => {
    window.clearTimeout(dismissTimer);
    dismissTimer = null;
};

const present = async ([, message]) => {
    presentation += 1;
    const currentPresentation = presentation;

    clearDismissTimer();
    visible.value = false;

    if (!message) return;

    await nextTick();
    if (currentPresentation !== presentation) return;

    visible.value = true;
    dismissTimer = window.setTimeout(() => {
        visible.value = false;
        dismissTimer = null;
    }, props.duration);
};

watch([() => props.flashId, () => props.message], present, { immediate: true });
onBeforeUnmount(clearDismissTimer);
</script>

<template>
    <Transition
        enter-active-class="transition-[opacity,transform] duration-150 ease-out motion-reduce:transition-none"
        enter-from-class="translate-y-1 opacity-0 motion-reduce:translate-y-0"
        leave-active-class="transition-[opacity,transform] duration-150 ease-in motion-reduce:transition-none"
        leave-to-class="translate-y-1 opacity-0 motion-reduce:translate-y-0"
    >
        <div
            v-if="visible && message"
            role="status"
            aria-live="polite"
            aria-atomic="true"
            class="pointer-events-none fixed right-4 top-[4.5rem] z-50 flex w-[calc(100%-2rem)] max-w-sm items-start gap-3 border border-border-strong bg-card px-4 py-3 text-sm text-foreground shadow-[var(--shadow)]"
        >
            <i class="bi bi-check-circle-fill mt-0.5 text-accent" aria-hidden="true"></i>
            <span class="min-w-0 leading-5">{{ message }}</span>
        </div>
    </Transition>
</template>
