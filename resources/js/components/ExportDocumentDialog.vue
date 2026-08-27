<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({ open: { type: Boolean, required: true }, busy: { type: Boolean, required: true } });
const emit = defineEmits(['close', 'export']);
const dialog = ref(null);
let previousFocus = null;
const close = () => { if (!props.busy) emit('close'); };
const keydown = (event) => { if (event.key === 'Escape') close(); };
watch(() => props.open, async (open) => {
    if (open) { previousFocus = document.activeElement; document.querySelector('#app')?.setAttribute('inert', ''); await nextTick(); dialog.value?.focus(); }
    else { document.querySelector('#app')?.removeAttribute('inert'); previousFocus?.focus?.(); previousFocus = null; }
});
onBeforeUnmount(() => { document.querySelector('#app')?.removeAttribute('inert'); });
</script>
<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[75] flex items-center justify-center bg-black/45 p-4" @mousedown.self="close">
            <div ref="dialog" role="dialog" aria-modal="true" aria-labelledby="export-document-title" tabindex="-1" class="w-full max-w-md border border-border-strong bg-background p-5 shadow-[var(--shadow)] outline-none">
                <div class="flex items-start justify-between gap-3"><div><p class="ui-eyebrow">Documento privado</p><h2 id="export-document-title" class="mt-1 text-lg font-semibold">Exportar documento</h2></div><button type="button" class="ui-button-ghost size-9 px-0" aria-label="Fechar exportação" :disabled="busy" @click="close"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
                <p class="mt-3 text-sm leading-6 text-muted-foreground">Escolha o formato para baixar o documento.</p>
                <div class="mt-4 grid gap-2">
                    <button v-for="option in [{ value: 'txt', label: 'Texto', ext: '.txt' }, { value: 'markdown', label: 'Markdown', ext: '.md' }, { value: 'html', label: 'HTML', ext: '.html' }, { value: 'pdf', label: 'PDF', ext: '.pdf' }, { value: 'docx', label: 'Word', ext: '.docx' }]" :key="option.value" type="button" class="flex items-center justify-between border border-border p-3 text-left hover:bg-muted disabled:opacity-50" :disabled="busy" @click="emit('export', option.value)"><span class="font-semibold">{{ option.label }}</span><span class="text-xs text-muted-foreground">{{ option.ext }}</span></button>
                </div>
                <button type="button" class="ui-button-secondary mt-4 w-full" :disabled="busy" @click="close">Cancelar</button>
            </div>
        </div>
    </Teleport>
</template>
