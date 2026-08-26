<script setup>
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import DocumentEditor from '../../components/DocumentEditor.vue';
import WorkspaceSource from '../../components/WorkspaceSource.vue';
import PublicLayout from '../../layouts/PublicLayout.vue';

const props = defineProps({ appName: { type: String, required: true }, workspace: { type: Object, required: true } });
const initial = props.workspace.document || props.workspace.seed;
const title = ref(initial.title);
const content = ref(JSON.parse(JSON.stringify(initial.content)));
const lockVersion = ref(props.workspace.document?.lockVersion ?? null);
const status = ref('saved');
const saving = ref(false);
const savePending = ref(false);
const activeMobilePanel = ref('document');
let saveTimer = null;
let allowNavigation = false;
let lastSavedSnapshot = JSON.stringify({ title: title.value, content: content.value });
const snapshot = () => JSON.stringify({ title: title.value, content: content.value });
const dirty = computed(() => snapshot() !== lastSavedSnapshot);
const unsafeToLeave = computed(() => dirty.value || saving.value);
const statusLabel = computed(() => ({ saved: 'Salvo', saving: 'Salvando...', dirty: 'Alterações não salvas', error: 'Falha ao salvar', conflict: 'Conflito de edição' }[status.value]));

const scheduleSave = () => {
    if (status.value === 'conflict') return;
    status.value = 'dirty';
    window.clearTimeout(saveTimer);
    saveTimer = window.setTimeout(saveNow, 800);
};
const updateContent = (value) => { content.value = value; scheduleSave(); };
const updateTitle = () => scheduleSave();
const saveNow = async () => {
    window.clearTimeout(saveTimer);
    if (status.value === 'conflict' || !dirty.value) return;
    if (saving.value) { savePending.value = true; return; }

    const sentSnapshot = snapshot();
    const payload = JSON.parse(sentSnapshot);
    saving.value = true;
    status.value = 'saving';

    try {
        const response = await axios.put(props.workspace.urls.save, { ...payload, lock_version: lockVersion.value }, { headers: { Accept: 'application/json' } });
        lockVersion.value = response.data.document.lockVersion;
        lastSavedSnapshot = sentSnapshot;
        status.value = snapshot() === sentSnapshot ? 'saved' : 'dirty';
        savePending.value = snapshot() !== sentSnapshot;
    } catch (error) {
        if (error.response?.status === 409 && error.response?.data?.code === 'document_conflict') {
            status.value = 'conflict';
        } else {
            status.value = 'error';
        }
        savePending.value = false;
    } finally {
        saving.value = false;
        if (savePending.value && status.value !== 'conflict') {
            savePending.value = false;
            saveNow();
        }
    }
};
const retry = () => { status.value = 'dirty'; saveNow(); };
const reloadLatest = () => { allowNavigation = true; window.location.reload(); };
const beforeUnload = (event) => {
    if (!unsafeToLeave.value) return;
    event.preventDefault();
    event.returnValue = '';
};
let removeInertiaGuard = null;

onMounted(() => {
    window.addEventListener('beforeunload', beforeUnload);
    removeInertiaGuard = router.on('before', (event) => {
        if (!allowNavigation && unsafeToLeave.value && !window.confirm('Há alterações não salvas. Deseja sair mesmo assim?')) event.preventDefault();
    });
});
onBeforeUnmount(() => {
    window.clearTimeout(saveTimer);
    window.removeEventListener('beforeunload', beforeUnload);
    removeInertiaGuard?.();
});
</script>

<template>
    <Head :title="title"><meta name="robots" content="noindex, nofollow" /></Head>
    <PublicLayout :app-name="appName">
        <div class="flex-1 bg-background">
            <div class="mx-auto max-w-[1500px] px-4 py-6 sm:px-8 lg:px-10">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
                    <div><Link :href="workspace.urls.library" class="ui-button-ghost px-0"><i class="bi bi-arrow-left" aria-hidden="true"></i> Biblioteca</Link><p class="ui-eyebrow mt-2">Workspace pessoal</p><h1 class="mt-1 text-xl font-semibold sm:text-2xl">{{ title }}</h1></div>
                    <Link :href="workspace.urls.show" class="ui-button-secondary"><i class="bi bi-eye" aria-hidden="true"></i> Ver original</Link>
                </header>

                <div class="mt-4 grid grid-cols-2 border border-border lg:hidden" role="tablist" aria-label="Áreas do Workspace">
                    <button id="document-tab" type="button" role="tab" class="min-h-11 text-sm font-semibold" :class="activeMobilePanel === 'document' ? 'bg-muted text-accent' : ''" :aria-selected="activeMobilePanel === 'document'" aria-controls="document-panel" @click="activeMobilePanel = 'document'">Documento</button>
                    <button id="source-tab" type="button" role="tab" class="min-h-11 border-l border-border text-sm font-semibold" :class="activeMobilePanel === 'source' ? 'bg-muted text-accent' : ''" :aria-selected="activeMobilePanel === 'source'" aria-controls="source-panel" @click="activeMobilePanel = 'source'">Fonte</button>
                </div>

                <div class="mt-5 grid min-w-0 gap-7 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                    <div id="source-panel" role="tabpanel" aria-labelledby="source-tab" class="min-w-0" :class="activeMobilePanel === 'source' ? 'block' : 'hidden lg:block'">
                        <WorkspaceSource :source="workspace.source" />
                    </div>
                    <section id="document-panel" role="tabpanel" aria-labelledby="document-tab document-panel-title" class="min-w-0" :class="activeMobilePanel === 'document' ? 'block' : 'hidden lg:block'">
                        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                            <div><p class="ui-eyebrow">Documento privado</p><h2 id="document-panel-title" class="mt-1 text-xl font-semibold">Documento</h2></div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-muted-foreground" aria-live="polite">{{ statusLabel }}</span>
                                <button v-if="status === 'error'" type="button" class="ui-button-secondary min-h-9 px-3 text-xs" @click="retry">Tentar novamente</button>
                                <button type="button" class="ui-button-secondary min-h-9 px-3 text-xs" :disabled="saving || status === 'conflict' || !dirty" @click="saveNow"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i> Salvar agora</button>
                            </div>
                        </div>
                        <div v-if="status === 'conflict'" class="mb-3 border border-accent bg-accent/[0.07] p-4 text-sm leading-6" role="alert">
                            <strong>Este documento foi alterado em outra aba.</strong> Seu conteúdo local continua visível e não será sobrescrito. Copie o que precisar antes de recarregar.
                            <button type="button" class="ui-button-secondary mt-3" @click="reloadLatest"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Recarregar versão mais recente</button>
                        </div>
                        <label for="document-title" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-muted-foreground">Título do documento</label>
                        <input id="document-title" v-model="title" type="text" maxlength="255" class="ui-input mb-3 text-base font-semibold" @input="updateTitle" />
                        <DocumentEditor :content="content" @update:content="updateContent" />
                        <p class="mt-3 text-xs leading-5 text-muted-foreground">Este documento é pessoal. Downloads atuais continuam usando a transcrição original.</p>
                    </section>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
