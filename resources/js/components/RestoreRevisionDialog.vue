<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({ open: { type: Boolean, required: true }, busy: { type: Boolean, required: true } });
const emit = defineEmits(['cancel', 'confirm']);
const dialog = ref(null);
const cancelButton = ref(null);
let previousFocus = null;

const cancel = () => {
    if (!props.busy) emit('cancel');
};
const handleKeydown = (event) => {
    if (event.key === 'Escape') {
        cancel();
        return;
    }
    if (event.key !== 'Tab' || !dialog.value) return;
    const focusable = [...dialog.value.querySelectorAll('button:not([disabled])')];
    if (focusable.length === 0) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};

watch(() => props.open, async (open) => {
    if (open) {
        previousFocus = document.activeElement;
        await nextTick();
        cancelButton.value?.focus();
    } else {
        previousFocus?.focus?.();
        previousFocus = null;
    }
});
onBeforeUnmount(() => previousFocus?.focus?.());
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[80] grid place-items-center bg-black/55 p-4" @mousedown.self="cancel">
            <section ref="dialog" role="alertdialog" aria-modal="true" aria-labelledby="restore-title" aria-describedby="restore-description" tabindex="-1" class="w-full max-w-md border border-border-strong bg-card p-5 shadow-[var(--shadow)] outline-none" @keydown="handleKeydown">
                <div class="flex items-start gap-3">
                    <i class="bi bi-arrow-counterclockwise mt-0.5 text-lg text-accent" aria-hidden="true"></i>
                    <div>
                        <h2 id="restore-title" class="text-lg font-semibold">Restaurar esta versão?</h2>
                        <p id="restore-description" class="mt-2 text-sm leading-6 text-foreground">A versão atual será preservada no histórico antes da restauração.</p>
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <button ref="cancelButton" type="button" class="ui-button-secondary" :disabled="busy" @click="cancel">Cancelar</button>
                    <button id="confirm-revision-restore" type="button" class="ui-button-primary" :disabled="busy" @click="emit('confirm')">
                        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> {{ busy ? 'Restaurando...' : 'Restaurar versão' }}
                    </button>
                </div>
            </section>
        </div>
    </Teleport>
</template>
