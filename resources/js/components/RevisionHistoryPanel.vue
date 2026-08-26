<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { formatDateTime } from '../utils/formatDate.js';
import RevisionPreview from './RevisionPreview.vue';

const props = defineProps({
    open: { type: Boolean, required: true },
    revisions: { type: Array, required: true },
    pagination: { type: Object, required: true },
    loading: { type: Boolean, required: true },
    error: { type: String, default: null },
    selectedPublicId: { type: String, default: null },
    selectedRevision: { type: Object, default: null },
    previewLoading: { type: Boolean, required: true },
    busy: { type: Boolean, required: true },
    suspended: { type: Boolean, required: true },
});
const emit = defineEmits(['close', 'select', 'create', 'restore', 'page']);
const panel = ref(null);
let previousFocus = null;

const labels = {
    baseline: 'Original',
    automatic: 'Versão automática',
    manual: 'Versão manual',
    restore_backup: 'Backup antes da restauração',
};

const close = () => {
    if (!props.busy) emit('close');
};
const handleKeydown = (event) => {
    if (event.key === 'Escape') {
        close();
        return;
    }
    if (event.key !== 'Tab' || !panel.value) return;

    const focusable = [...panel.value.querySelectorAll('button:not([disabled]), [href], [tabindex]:not([tabindex="-1"])')];
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
        document.querySelector('#app')?.setAttribute('inert', '');
        await nextTick();
        panel.value?.focus();
    } else {
        document.querySelector('#app')?.removeAttribute('inert');
        previousFocus?.focus?.();
        previousFocus = null;
    }
});
onBeforeUnmount(() => {
    document.querySelector('#app')?.removeAttribute('inert');
    previousFocus?.focus?.();
});
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[70] flex justify-end bg-black/45" @mousedown.self="close">
            <div ref="panel" role="dialog" aria-modal="true" aria-labelledby="revision-history-title" :inert="suspended ? '' : null" :aria-hidden="suspended ? 'true' : null" tabindex="-1" class="flex h-full w-full max-w-2xl flex-col border-l border-border-strong bg-background shadow-[var(--shadow)] outline-none" @keydown="handleKeydown">
                <div class="flex items-center justify-between gap-3 border-b border-border px-4 py-3 sm:px-5">
                    <div>
                        <p class="ui-eyebrow">Documento privado</p>
                        <h2 id="revision-history-title" class="mt-1 text-lg font-semibold">Histórico de versões</h2>
                    </div>
                    <button type="button" class="ui-button-ghost size-10 px-0 text-lg" aria-label="Fechar histórico" title="Fechar" :disabled="busy" @click="close">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="flex min-h-0 flex-1 flex-col overflow-y-auto p-4 sm:p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
                        <p class="max-w-md text-sm leading-5 text-muted-foreground">Snapshots privados e imutáveis do documento. Versões automáticas são espaçadas para evitar ruído.</p>
                        <button id="create-manual-revision" type="button" class="ui-button-secondary" :disabled="busy" @click="emit('create')">
                            <i class="bi bi-bookmark-plus" aria-hidden="true"></i> Criar versão
                        </button>
                    </div>

                    <p v-if="error" class="mt-4 border border-accent bg-accent/[0.07] p-3 text-sm" role="alert">{{ error }}</p>
                    <p v-if="loading" class="py-8 text-center text-sm text-muted-foreground" role="status">Carregando histórico...</p>
                    <p v-else-if="revisions.length === 0" class="py-8 text-center text-sm text-muted-foreground">O histórico será iniciado no primeiro salvamento.</p>

                    <div v-else class="mt-4 grid min-h-0 gap-5 md:grid-cols-[minmax(13rem,0.72fr)_minmax(0,1.28fr)]">
                        <div class="min-w-0">
                            <ul class="space-y-2" aria-label="Versões do documento">
                                <li v-for="revision in revisions" :key="revision.publicId">
                                    <button :id="`revision-${revision.kind}-${revision.publicId}`" type="button" class="w-full border border-border p-3 text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus" :class="selectedPublicId === revision.publicId ? 'bg-muted border-border-strong' : 'bg-card hover:bg-muted/60'" :aria-pressed="selectedPublicId === revision.publicId" :disabled="busy" @click="emit('select', revision)">
                                        <span class="flex items-start justify-between gap-2">
                                            <strong class="text-sm">{{ labels[revision.kind] }}</strong>
                                            <span class="text-xs text-muted-foreground">#{{ revision.revisionNumber }}</span>
                                        </span>
                                        <span class="mt-1 block text-xs text-muted-foreground">{{ formatDateTime(revision.createdAt) }}</span>
                                        <span class="mt-2 line-clamp-2 block text-xs leading-5 text-foreground">{{ revision.preview || 'Documento vazio' }}</span>
                                    </button>
                                </li>
                            </ul>
                            <nav v-if="pagination.lastPage > 1" class="mt-4 flex items-center justify-between gap-2" aria-label="Paginação do histórico">
                                <button type="button" class="ui-button-secondary min-h-9 px-3 text-xs" :disabled="loading || pagination.currentPage <= 1" @click="emit('page', pagination.currentPage - 1)">Anterior</button>
                                <span class="text-xs text-muted-foreground">{{ pagination.currentPage }} de {{ pagination.lastPage }}</span>
                                <button type="button" class="ui-button-secondary min-h-9 px-3 text-xs" :disabled="loading || pagination.currentPage >= pagination.lastPage" @click="emit('page', pagination.currentPage + 1)">Próxima</button>
                            </nav>
                        </div>

                        <section class="min-w-0" aria-label="Visualização da versão selecionada">
                            <p v-if="previewLoading" class="py-8 text-center text-sm text-muted-foreground" role="status">Carregando versão...</p>
                            <template v-else-if="selectedRevision">
                                <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="ui-eyebrow">{{ labels[selectedRevision.kind] }} · #{{ selectedRevision.revisionNumber }}</p>
                                        <h3 id="revision-preview-title" class="mt-1 text-base font-semibold">{{ selectedRevision.title }}</h3>
                                        <p class="mt-1 text-xs text-muted-foreground">{{ formatDateTime(selectedRevision.createdAt) }}</p>
                                    </div>
                                    <button id="restore-selected-revision" type="button" class="ui-button-secondary" :disabled="busy" @click="emit('restore', selectedRevision)">
                                        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Restaurar esta versão
                                    </button>
                                </div>
                                <RevisionPreview :content="selectedRevision.content" />
                            </template>
                            <p v-else class="border border-dashed border-border p-5 text-sm leading-6 text-foreground">Selecione uma versão para visualizar o conteúdo completo.</p>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
