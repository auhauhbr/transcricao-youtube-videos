<script setup>
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import DocumentEditor from '../../components/DocumentEditor.vue';
import ExportDocumentDialog from '../../components/ExportDocumentDialog.vue';
import FlashToast from '../../components/FlashToast.vue';
import RestoreRevisionDialog from '../../components/RestoreRevisionDialog.vue';
import RevisionHistoryPanel from '../../components/RevisionHistoryPanel.vue';
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
const historyOpen = ref(false);
const historyLoaded = ref(false);
const historyLoading = ref(false);
const historyError = ref(null);
const revisions = ref([]);
const revisionPagination = ref({ currentPage: 1, lastPage: 1, perPage: 20, total: 0 });
const selectedRevision = ref(null);
const selectedRevisionPublicId = ref(null);
const previewLoading = ref(false);
const revisionActionBusy = ref(false);
const restoreTarget = ref(null);
const feedback = ref(null);
const feedbackId = ref(0);
const exportOpen = ref(false);
const exportBusy = ref(false);
let saveTimer = null;
let inFlightSave = null;
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
const showFeedback = (message) => {
    feedback.value = message;
    feedbackId.value += 1;
};
const markConflict = () => {
    status.value = 'conflict';
    savePending.value = false;
};
const loadHistory = async (page = 1) => {
    historyLoading.value = true;
    historyError.value = null;
    try {
        const response = await axios.get(props.workspace.urls.revisions, {
            params: { page },
            headers: { Accept: 'application/json' },
        });
        revisions.value = response.data.data;
        revisionPagination.value = response.data.meta;
        historyLoaded.value = true;
        selectedRevision.value = null;
        selectedRevisionPublicId.value = null;
    } catch {
        historyError.value = 'Não foi possível carregar o histórico.';
    } finally {
        historyLoading.value = false;
    }
};
const saveNow = () => {
    window.clearTimeout(saveTimer);
    if (status.value === 'conflict' || !dirty.value) return Promise.resolve(!dirty.value);
    if (saving.value) {
        savePending.value = true;
        return inFlightSave || Promise.resolve(false);
    }

    const sentSnapshot = snapshot();
    const payload = JSON.parse(sentSnapshot);
    saving.value = true;
    status.value = 'saving';

    inFlightSave = (async () => {
        try {
            const response = await axios.put(props.workspace.urls.save, { ...payload, lock_version: lockVersion.value }, { headers: { Accept: 'application/json' } });
            lockVersion.value = response.data.document.lockVersion;
            lastSavedSnapshot = sentSnapshot;
            status.value = snapshot() === sentSnapshot ? 'saved' : 'dirty';
            savePending.value = snapshot() !== sentSnapshot;
            if (response.data.automaticRevisionCreated && historyLoaded.value) {
                await loadHistory(1);
            }
        } catch (error) {
            if (error.response?.status === 409 && error.response?.data?.code === 'document_conflict') {
                markConflict();
            } else {
                status.value = 'error';
            }
            savePending.value = false;
        } finally {
            saving.value = false;
        }

        if (savePending.value && status.value !== 'conflict') {
            savePending.value = false;
            inFlightSave = null;
            return saveNow();
        }

        inFlightSave = null;
        return status.value === 'saved' && !dirty.value;
    })();

    return inFlightSave;
};
const retry = () => { status.value = 'dirty'; saveNow(); };
const reloadLatest = () => { allowNavigation = true; window.location.reload(); };
const ensureSaved = async () => {
    if (status.value === 'conflict') return false;
    if (saving.value && inFlightSave) await inFlightSave;
    if (dirty.value) await saveNow();
    return status.value === 'saved' && !dirty.value && !saving.value;
};
const exportDocument = async (format) => {
    exportBusy.value = true;
    const saved = await ensureSaved();
    if (!saved || lockVersion.value === null) {
        showFeedback(saved ? 'Salve o documento antes de exportar.' : 'As alterações precisam ser salvas antes da exportação.');
        exportBusy.value = false;
        return;
    }
    exportOpen.value = false;
    const link = document.createElement('a');
    link.href = `${props.workspace.urls.export}?format=${encodeURIComponent(format)}`;
    link.download = '';
    document.body.appendChild(link);
    link.click();
    link.remove();
    exportBusy.value = false;
};
const openHistory = () => {
    historyOpen.value = true;
    if (!historyLoaded.value) loadHistory();
};
const selectRevision = async (revision) => {
    selectedRevisionPublicId.value = revision.publicId;
    selectedRevision.value = null;
    previewLoading.value = true;
    historyError.value = null;
    try {
        const response = await axios.get(revision.urls.show, { headers: { Accept: 'application/json' } });
        if (selectedRevisionPublicId.value === revision.publicId) {
            selectedRevision.value = { ...response.data.revision, urls: revision.urls };
        }
    } catch {
        historyError.value = 'Não foi possível carregar esta versão.';
    } finally {
        if (selectedRevisionPublicId.value === revision.publicId) previewLoading.value = false;
    }
};
const createManualRevision = async () => {
    if (lockVersion.value === null && !dirty.value) {
        showFeedback('Faça uma alteração no documento antes de criar uma versão.');
        return;
    }
    revisionActionBusy.value = true;
    historyError.value = null;
    const saved = await ensureSaved();
    if (!saved) {
        historyError.value = status.value === 'conflict'
            ? 'Resolva o conflito antes de criar uma versão.'
            : 'As alterações precisam ser salvas antes de criar uma versão.';
        revisionActionBusy.value = false;
        return;
    }
    try {
        const response = await axios.post(props.workspace.urls.createRevision, {
            expected_lock_version: lockVersion.value,
        }, { headers: { Accept: 'application/json' } });
        showFeedback(response.data.created ? 'Versão criada.' : 'Nenhuma alteração desde a última versão.');
        await loadHistory(1);
    } catch (error) {
        if (error.response?.status === 409 && error.response?.data?.code === 'document_conflict') {
            markConflict();
            historyError.value = 'Este documento foi alterado em outra aba. Recarregue a versão mais recente antes de continuar.';
        } else {
            historyError.value = 'Não foi possível criar a versão.';
        }
    } finally {
        revisionActionBusy.value = false;
    }
};
const prepareRestore = async (revision) => {
    revisionActionBusy.value = true;
    const saved = await ensureSaved();
    revisionActionBusy.value = false;
    if (saved) {
        restoreTarget.value = revision;
    } else {
        historyError.value = status.value === 'conflict'
            ? 'Resolva o conflito antes de restaurar uma versão.'
            : 'As alterações precisam ser salvas antes da restauração.';
    }
};
const confirmRestore = async () => {
    if (!restoreTarget.value) return;
    revisionActionBusy.value = true;
    historyError.value = null;
    try {
        const response = await axios.post(restoreTarget.value.urls.restore, {
            expected_lock_version: lockVersion.value,
        }, { headers: { Accept: 'application/json' } });
        const document = response.data.document;
        window.clearTimeout(saveTimer);
        title.value = document.title;
        content.value = JSON.parse(JSON.stringify(document.content));
        lockVersion.value = document.lockVersion;
        lastSavedSnapshot = snapshot();
        savePending.value = false;
        status.value = 'saved';
        showFeedback(response.data.restored ? 'Versão restaurada.' : 'O documento já corresponde a esta versão.');
        restoreTarget.value = null;
        await loadHistory(1);
    } catch (error) {
        restoreTarget.value = null;
        if (error.response?.status === 409 && error.response?.data?.code === 'document_conflict') {
            markConflict();
            historyError.value = 'Este documento foi alterado em outra aba. Recarregue a versão mais recente antes de continuar.';
        } else {
            historyError.value = 'Não foi possível restaurar a versão.';
        }
    } finally {
        revisionActionBusy.value = false;
    }
};
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
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="ui-button-secondary" aria-label="Exportar documento editado" @click="exportOpen = true"><i class="bi bi-download" aria-hidden="true"></i> Exportar</button>
                        <button type="button" class="ui-button-secondary" aria-label="Abrir histórico de versões" @click="openHistory"><i class="bi bi-clock-history" aria-hidden="true"></i> Histórico</button>
                        <Link :href="workspace.urls.show" class="ui-button-secondary"><i class="bi bi-eye" aria-hidden="true"></i> Ver original</Link>
                    </div>
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
        <FlashToast :flash-id="String(feedbackId)" :message="feedback" />
        <RevisionHistoryPanel
            :open="historyOpen"
            :revisions="revisions"
            :pagination="revisionPagination"
            :loading="historyLoading"
            :error="historyError"
            :selected-public-id="selectedRevisionPublicId"
            :selected-revision="selectedRevision"
            :preview-loading="previewLoading"
            :busy="revisionActionBusy"
            :suspended="restoreTarget !== null"
            @close="historyOpen = false"
            @select="selectRevision"
            @create="createManualRevision"
            @restore="prepareRestore"
            @page="loadHistory"
        />
        <RestoreRevisionDialog :open="restoreTarget !== null" :busy="revisionActionBusy" @cancel="restoreTarget = null" @confirm="confirmRestore" />
        <ExportDocumentDialog :open="exportOpen" :busy="exportBusy" @close="exportOpen = false" @export="exportDocument" />
    </PublicLayout>
</template>
