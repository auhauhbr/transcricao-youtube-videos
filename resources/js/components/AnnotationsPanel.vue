<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { formatTimestamp } from '../utils/formatTimestamp.js';

const props = defineProps({ open: { type: Boolean, required: true }, items: { type: Array, required: true }, busy: { type: Boolean, default: false } });
const emit = defineEmits(['close', 'navigate', 'edit-note', 'delete', 'toggle-bookmark']);
const filter = ref('all');
const query = ref('');
const panel = ref(null);
const closeButton = ref(null);
const filteredItems = computed(() => props.items.filter((item) => (filter.value === 'all' || item.type === filter.value) && (!query.value || item.text?.toLocaleLowerCase('pt-BR').includes(query.value.toLocaleLowerCase('pt-BR')))));

watch(() => props.open, async (open) => { if (open) { await nextTick(); closeButton.value?.focus(); } });
const close = () => { if (!props.busy) emit('close'); };
const handleKeydown = (event) => {
    if (event.key === 'Escape') {
        close();
        return;
    }
    if (event.key !== 'Tab' || !panel.value) return;
    const focusable = [...panel.value.querySelectorAll('button:not([disabled]), input:not([disabled])')];
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
        <div v-if="open" class="fixed inset-0 z-[70] bg-black/45" @mousedown.self="close">
            <section ref="panel" role="dialog" aria-modal="true" aria-labelledby="annotations-panel-title" class="ml-auto flex h-full w-full max-w-md flex-col border-l border-border-strong bg-background shadow-[var(--shadow)]" @keydown="handleKeydown">
                <header class="flex items-start justify-between gap-4 border-b border-border p-5"><div><p class="ui-eyebrow">Privado</p><h2 id="annotations-panel-title" class="mt-1 text-lg font-semibold">Notas e marcadores</h2></div><button ref="closeButton" type="button" class="ui-button-ghost size-9 px-0" :disabled="busy" aria-label="Fechar notas e marcadores" @click="close"><i class="bi bi-x-lg" aria-hidden="true"></i></button></header>
                <div class="border-b border-border p-4"><div class="grid grid-cols-3 border border-border text-sm"><button v-for="option in [['all', 'Todos'], ['note', 'Notas'], ['bookmark', 'Marcadores']]" :key="option[0]" type="button" class="min-h-10 border-r border-border last:border-r-0" :class="filter === option[0] ? 'bg-muted font-semibold text-accent' : ''" :aria-pressed="filter === option[0]" @click="filter = option[0]">{{ option[1] }}</button></div><label for="annotation-search" class="sr-only">Buscar nas notas</label><input id="annotation-search" v-model="query" type="search" class="ui-input mt-3 text-sm" placeholder="Buscar nas notas" /></div>
                <ol class="min-h-0 flex-1 divide-y divide-border overflow-y-auto">
                    <li v-for="item in filteredItems" :key="item.publicId" class="p-4">
                        <div class="flex items-start justify-between gap-3"><button type="button" class="font-mono text-xs font-semibold text-accent hover:underline" :aria-label="`Ir para ${formatTimestamp(item.startMs)}`" @click="emit('navigate', item.startMs)">{{ formatTimestamp(item.startMs) }}</button><div class="flex items-center gap-1"><button v-if="item.type === 'bookmark'" type="button" class="ui-button-ghost size-8 px-0" :disabled="busy" aria-label="Remover marcador" title="Remover marcador" @click="emit('toggle-bookmark', item.startMs)"><i class="bi bi-bookmark-fill text-accent" aria-hidden="true"></i></button><button v-else type="button" class="ui-button-ghost size-8 px-0" :disabled="busy" aria-label="Editar nota" title="Editar nota" @click="emit('edit-note', item.startMs)"><i class="bi bi-pencil" aria-hidden="true"></i></button><button type="button" class="ui-button-ghost size-8 px-0 text-destructive" :disabled="busy" :aria-label="item.type === 'note' ? 'Excluir nota' : 'Excluir marcador'" title="Excluir" @click="emit('delete', item)"><i class="bi bi-trash3" aria-hidden="true"></i></button></div></div>
                        <p class="mt-2 text-xs font-semibold uppercase tracking-[0.12em] text-muted-foreground"><i :class="['bi mr-1', item.type === 'note' ? 'bi-journal-text' : 'bi-bookmark']" aria-hidden="true"></i>{{ item.type === 'note' ? 'Nota' : 'Marcador' }}</p><p v-if="item.text" class="mt-2 whitespace-pre-wrap text-sm leading-6 text-foreground">{{ item.text }}</p>
                    </li>
                    <li v-if="filteredItems.length === 0" class="p-6 text-center text-sm text-muted-foreground">Nenhuma annotation encontrada.</li>
                </ol>
            </section>
        </div>
    </Teleport>
</template>
