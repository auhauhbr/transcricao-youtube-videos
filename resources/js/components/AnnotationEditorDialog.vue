<script setup>
import { nextTick, ref, watch } from 'vue';
import { formatTimestamp } from '../utils/formatTimestamp.js';

const props = defineProps({ open: { type: Boolean, required: true }, startMs: { type: Number, default: null }, text: { type: String, default: '' }, busy: { type: Boolean, default: false } });
const emit = defineEmits(['cancel', 'save']);
const dialog = ref(null);
const input = ref(null);
const value = ref('');

watch(() => props.open, async (open) => {
    if (!open) return;
    value.value = props.text;
    await nextTick();
    input.value?.focus();
});
const handleKeydown = (event) => {
    if (event.key === 'Escape' && !props.busy) {
        emit('cancel');
        return;
    }
    if (event.key !== 'Tab' || !dialog.value) return;
    const focusable = [...dialog.value.querySelectorAll('button:not([disabled]), textarea:not([disabled])')];
    const first = focusable[0];
    const last = focusable.at(-1);
    if (!first || !last) return;
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[80] grid place-items-center bg-black/55 p-4" @mousedown.self="!busy && emit('cancel')">
            <section ref="dialog" role="dialog" aria-modal="true" aria-labelledby="annotation-editor-title" class="w-full max-w-lg border border-border-strong bg-card p-5 shadow-[var(--shadow)]" @keydown="handleKeydown">
                <div class="flex items-start justify-between gap-4">
                    <div><p class="ui-eyebrow">{{ startMs === null ? '' : formatTimestamp(startMs) }}</p><h2 id="annotation-editor-title" class="mt-1 text-lg font-semibold">{{ text ? 'Editar nota' : 'Adicionar nota' }}</h2></div>
                    <button type="button" class="ui-button-ghost size-9 px-0" :disabled="busy" aria-label="Fechar editor de nota" @click="emit('cancel')"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                </div>
                <label for="annotation-text" class="mt-5 block text-sm font-semibold">Nota privada</label>
                <textarea id="annotation-text" ref="input" v-model="value" maxlength="4000" rows="6" class="ui-input mt-2 resize-y text-sm leading-6" :disabled="busy" placeholder="Escreva uma observação para este trecho..."></textarea>
                <p class="mt-1 text-right text-xs text-muted-foreground">{{ value.length }}/4000</p>
                <div class="mt-5 flex justify-end gap-2"><button type="button" class="ui-button-secondary" :disabled="busy" @click="emit('cancel')">Cancelar</button><button type="button" class="ui-button-primary" :disabled="busy || !value.trim()" @click="emit('save', value)"><i class="bi bi-check-lg" aria-hidden="true"></i> {{ busy ? 'Salvando...' : 'Salvar nota' }}</button></div>
            </section>
        </div>
    </Teleport>
</template>
