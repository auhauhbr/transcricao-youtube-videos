<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import FlashToast from '../../components/FlashToast.vue';
import PublicLayout from '../../layouts/PublicLayout.vue';
import { formatDate } from '../../utils/formatDate.js';
import { formatTimestamp } from '../../utils/formatTimestamp.js';

const props = defineProps({ appName: { type: String, required: true }, flash: { type: Object, required: true }, library: { type: Object, required: true } });
const search = ref(props.library.filters.q || '');
const filtersOpen = ref(false);
const organizationDialog = ref(null);
const resourceDialog = ref(null);
const actionDialog = ref(null);
const nameInput = ref(null);
const selected = ref([]);
const openMenuId = ref(null);
const menuTriggers = new Map();
const resourceMode = ref('create-folder');
const resourceTarget = ref(null);
const actionMode = ref('move');
const actionItemIds = ref([]);
const selectedFolder = ref('');
const selectedTags = ref([]);
const tagOperation = ref('add');
let searchTimer = null;
let returnFocus = null;
const resourceForm = useForm({ name: '' });
const actionForm = useForm({ item_public_ids: [], folder_public_id: null, tag_public_ids: [] });
const visibleIds = computed(() => props.library.items.map((item) => item.publicId));
const selectedCount = computed(() => selected.value.length);
const pageSelected = computed(() => visibleIds.value.length > 0 && visibleIds.value.every((id) => selected.value.includes(id)));
const activeFilters = computed(() => {
    const active = [];
    const current = props.library.filters;
    if (current.folder) {
        const folder = props.library.folders.find((entry) => entry.publicId === current.folder);
        active.push(`Pasta: ${current.folder === 'none' ? 'Sem pasta' : folder?.name || 'Pasta'}`);
    }
    if (current.tag) active.push(`Tag: ${props.library.tags.find((entry) => entry.publicId === current.tag)?.name || 'Tag'}`);
    if (current.language) active.push(`Idioma: ${props.library.languages.find((entry) => entry.code === current.language)?.label || current.language}`);
    if (current.source) active.push(`Origem: ${current.source === 'manual' ? 'Legendas manuais' : 'Legendas automáticas'}`);
    return active;
});
const params = (changes = {}) => {
    const values = { ...props.library.filters, q: search.value.trim(), ...changes };
    return Object.fromEntries(Object.entries(values).filter(([, value]) => value !== null && value !== '' && value !== 'newest'));
};
const visit = (changes = {}, options = {}) => {
    selected.value = [];
    openMenuId.value = null;
    router.get('/library', params(changes), { preserveState: true, preserveScroll: true, replace: true, ...options });
};
watch(search, () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => visit({ q: search.value.trim() }), 350);
});
watch(() => props.library.filters.q, (value) => { if ((value || '') !== search.value) search.value = value || ''; });
watch(() => props.library.pagination.currentPage, () => { selected.value = []; });
const togglePage = () => { selected.value = pageSelected.value ? [] : [...visibleIds.value]; };
const toggleItem = (id) => { selected.value = selected.value.includes(id) ? selected.value.filter((selectedId) => selectedId !== id) : [...selected.value, id]; };
const clearFilters = () => { search.value = ''; visit({ q: '', folder: null, tag: null, language: null, source: null, sort: props.library.filters.sort }); };
const toggleMenu = async (item, trigger) => {
    if (openMenuId.value === item.publicId) { openMenuId.value = null; return; }
    menuTriggers.set(item.publicId, trigger);
    openMenuId.value = item.publicId;
    await nextTick();
    document.querySelector(`[data-menu="${item.publicId}"] [role="menuitem"]`)?.focus();
};
const closeMenu = (restore = false) => {
    const previous = openMenuId.value;
    openMenuId.value = null;
    if (restore && previous) nextTick(() => menuTriggers.get(previous)?.focus());
};
const handleDocumentClick = (event) => { if (openMenuId.value && !event.target.closest('[data-item-menu]')) closeMenu(); };
const handleEscape = (event) => { if (event.key === 'Escape' && openMenuId.value) { event.preventDefault(); closeMenu(true); } };
const openResource = async (mode, target = null, trigger = document.activeElement) => {
    resourceMode.value = mode;
    resourceTarget.value = target;
    resourceForm.clearErrors();
    resourceForm.name = target?.name || '';
    returnFocus = trigger instanceof HTMLElement ? trigger : null;
    resourceDialog.value.showModal();
    await nextTick();
    nameInput.value?.focus();
};
const resourceLabels = computed(() => ({
    'create-folder': ['Nova pasta', 'Nome da pasta', 'Criar'],
    'rename-folder': ['Renomear pasta', 'Nome da pasta', 'Salvar'],
    'delete-folder': [`Excluir pasta “${resourceTarget.value?.name || ''}”?`, '', 'Excluir pasta'],
    'create-tag': ['Nova tag', 'Nome da tag', 'Criar'],
    'rename-tag': ['Renomear tag', 'Nome da tag', 'Salvar'],
    'delete-tag': [`Excluir tag “${resourceTarget.value?.name || ''}”?`, '', 'Excluir tag'],
}[resourceMode.value]));
const submitResource = () => {
    const [action, type] = resourceMode.value.split('-');
    const base = type === 'folder' ? '/library/folders' : '/library/tags';
    const url = action === 'create' ? base : `${base}/${resourceTarget.value.publicId}`;
    const method = action === 'create' ? 'post' : action === 'rename' ? 'patch' : 'delete';
    resourceForm[method](url, { preserveScroll: true, onSuccess: () => resourceDialog.value.close() });
};
const openAction = (mode, ids, trigger = document.activeElement) => {
    const menuTrigger = ids.length === 1 ? menuTriggers.get(ids[0]) : null;
    closeMenu();
    actionMode.value = mode;
    actionItemIds.value = [...ids];
    selectedFolder.value = '';
    selectedTags.value = [];
    tagOperation.value = 'add';
    actionForm.clearErrors();
    returnFocus = menuTrigger?.isConnected ? menuTrigger : trigger instanceof HTMLElement ? trigger : null;
    actionDialog.value.showModal();
};
const submitAction = () => {
    actionForm.item_public_ids = actionItemIds.value;
    const callbacks = { preserveScroll: true, onSuccess: () => { actionDialog.value.close(); selected.value = []; } };
    if (actionMode.value === 'move') { actionForm.folder_public_id = selectedFolder.value || null; actionForm.patch('/library/items/move', callbacks); return; }
    if (actionMode.value === 'tags') { actionForm.tag_public_ids = selectedTags.value; actionForm[tagOperation.value === 'add' ? 'post' : 'delete']('/library/items/tags', callbacks); return; }
    actionForm.delete('/library/items', callbacks);
};
const restoreDialogFocus = () => { if (returnFocus?.isConnected) returnFocus.focus(); returnFocus = null; };
onMounted(() => { document.addEventListener('click', handleDocumentClick); document.addEventListener('keydown', handleEscape); });
onBeforeUnmount(() => { window.clearTimeout(searchTimer); document.removeEventListener('click', handleDocumentClick); document.removeEventListener('keydown', handleEscape); });
</script>

<template>
    <Head title="Biblioteca"><meta name="robots" content="noindex, nofollow" /></Head>
    <PublicLayout :app-name="appName">
        <FlashToast :flash-id="flash.id" :message="flash.message" />
        <section class="flex-1 border-b border-border bg-background">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-8 sm:py-8 lg:px-10">
                <header class="flex flex-wrap items-end justify-between gap-4 border-b border-border pb-5">
                    <div><p class="ui-eyebrow">Organização pessoal</p><h1 class="mt-2 text-3xl font-semibold tracking-tight text-foreground">Biblioteca</h1><p class="mt-1.5 text-sm text-muted-foreground">{{ library.counts.all }} {{ library.counts.all === 1 ? 'transcrição' : 'transcrições' }}</p></div>
                    <Link href="/" class="ui-button-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nova transcrição</Link>
                </header>
                <div class="mt-5 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
                    <label class="relative block"><span class="sr-only">Buscar na biblioteca</span><i class="bi bi-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground" aria-hidden="true"></i><input v-model="search" type="search" placeholder="Buscar na biblioteca..." class="ui-input pl-9 text-sm placeholder:text-muted-foreground" /></label>
                    <button type="button" class="ui-button-secondary" :aria-expanded="filtersOpen" @click="filtersOpen = !filtersOpen"><i class="bi bi-funnel" aria-hidden="true"></i> Filtros</button>
                    <label class="sr-only" for="library-sort">Ordenar biblioteca</label><select id="library-sort" :value="library.filters.sort" class="ui-input w-auto min-w-40 text-sm font-semibold" @change="visit({ sort: $event.target.value })"><option value="newest">Mais recentes</option><option value="oldest">Mais antigas</option><option value="title_asc">Título A–Z</option><option value="title_desc">Título Z–A</option></select>
                </div>
                <div v-if="filtersOpen" class="ui-panel mt-3 grid gap-3 p-4 sm:grid-cols-3">
                    <label class="text-xs font-semibold text-muted-foreground">Idioma<select :value="library.filters.language || ''" class="ui-input mt-1.5 text-sm" @change="visit({ language: $event.target.value || null })"><option value="">Todos</option><option v-for="language in library.languages" :key="language.code" :value="language.code">{{ language.label }}</option></select></label>
                    <label class="text-xs font-semibold text-muted-foreground">Origem<select :value="library.filters.source || ''" class="ui-input mt-1.5 text-sm" @change="visit({ source: $event.target.value || null })"><option value="">Todas</option><option value="manual">Legendas manuais</option><option value="automatic">Legendas automáticas</option></select></label>
                    <label class="text-xs font-semibold text-muted-foreground">Tag<select :value="library.filters.tag || ''" class="ui-input mt-1.5 text-sm" @change="visit({ tag: $event.target.value || null })"><option value="">Todas</option><option v-for="tag in library.tags" :key="tag.publicId" :value="tag.publicId">{{ tag.name }}</option></select></label>
                </div>
                <div v-if="activeFilters.length || library.filters.q" class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-muted-foreground"><span v-if="library.filters.q">Busca: “{{ library.filters.q }}”</span><span v-for="filter in activeFilters" :key="filter">{{ filter }}</span><button type="button" class="font-semibold text-accent hover:underline" @click="clearFilters">Limpar filtros</button></div>
                <button type="button" class="ui-button-secondary mt-5 lg:hidden" @click="organizationDialog.showModal()"><i class="bi bi-folder2-open" aria-hidden="true"></i> Pastas e tags</button>
                <div class="mt-5 grid min-w-0 gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
                    <aside class="hidden min-w-0 border-r border-border pr-5 lg:block" aria-label="Organização da biblioteca">
                        <nav class="space-y-1"><button type="button" class="flex w-full items-center justify-between border-l-2 border-transparent px-2 py-2 text-left text-sm font-semibold" :class="!library.filters.folder ? 'border-l-accent bg-muted text-foreground' : ''" @click="visit({ folder: null })"><span>Todas</span><span>{{ library.counts.all }}</span></button><button type="button" class="flex w-full items-center justify-between border-l-2 border-transparent px-2 py-2 text-left text-sm" :class="library.filters.folder === 'none' ? 'border-l-accent bg-muted text-foreground' : ''" @click="visit({ folder: 'none' })"><span>Sem pasta</span><span>{{ library.counts.unfiled }}</span></button></nav>
                        <div class="mt-6 flex items-center justify-between"><h2 class="text-[11px] font-bold uppercase tracking-[0.16em] text-muted-foreground"><i class="bi bi-folder mr-1" aria-hidden="true"></i> Pastas</h2><button type="button" class="text-xs font-semibold text-accent" @click="openResource('create-folder', null, $event.currentTarget)"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nova</button></div>
                        <ul class="mt-2 space-y-1"><li v-for="folder in library.folders" :key="folder.publicId" class="flex min-w-0 items-center"><button type="button" class="flex min-w-0 flex-1 items-center justify-between gap-2 border-l-2 border-transparent px-2 py-2 text-left text-sm hover:bg-muted" :class="library.filters.folder === folder.publicId ? 'border-l-accent bg-muted text-foreground' : ''" @click="visit({ folder: folder.publicId })"><span class="truncate">{{ folder.name }}</span><span>{{ folder.count }}</span></button><button type="button" class="ui-button-ghost size-8 px-0" :aria-label="`Renomear pasta ${folder.name}`" :title="`Renomear ${folder.name}`" @click="openResource('rename-folder', folder, $event.currentTarget)"><i class="bi bi-pencil" aria-hidden="true"></i></button><button type="button" class="ui-button-ghost size-8 px-0 text-destructive" :aria-label="`Excluir pasta ${folder.name}`" :title="`Excluir ${folder.name}`" @click="openResource('delete-folder', folder, $event.currentTarget)"><i class="bi bi-trash" aria-hidden="true"></i></button></li></ul>
                        <div class="mt-6 flex items-center justify-between"><h2 class="text-[11px] font-bold uppercase tracking-[0.16em] text-muted-foreground"><i class="bi bi-tag mr-1" aria-hidden="true"></i> Tags</h2><button type="button" class="text-xs font-semibold text-accent" @click="openResource('create-tag', null, $event.currentTarget)"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nova</button></div>
                        <ul class="mt-2 space-y-1"><li v-for="tag in library.tags" :key="tag.publicId" class="flex min-w-0 items-center"><button type="button" class="min-w-0 flex-1 truncate border-l-2 border-transparent px-2 py-2 text-left text-sm hover:bg-muted" :class="library.filters.tag === tag.publicId ? 'border-l-accent bg-muted text-foreground' : ''" @click="visit({ tag: tag.publicId })">{{ tag.name }}</button><button type="button" class="ui-button-ghost size-8 px-0" :aria-label="`Renomear tag ${tag.name}`" :title="`Renomear ${tag.name}`" @click="openResource('rename-tag', tag, $event.currentTarget)"><i class="bi bi-pencil" aria-hidden="true"></i></button><button type="button" class="ui-button-ghost size-8 px-0 text-destructive" :aria-label="`Excluir tag ${tag.name}`" :title="`Excluir ${tag.name}`" @click="openResource('delete-tag', tag, $event.currentTarget)"><i class="bi bi-trash" aria-hidden="true"></i></button></li></ul>
                    </aside>
                    <div class="min-w-0">
                        <div v-if="selectedCount" class="sticky top-[3.75rem] z-30 mb-3 flex flex-wrap items-center gap-2 border border-border-strong bg-panel-secondary p-3" aria-live="polite"><strong class="mr-auto text-sm">{{ selectedCount }} {{ selectedCount === 1 ? 'selecionada' : 'selecionadas' }}</strong><button type="button" class="ui-button-secondary min-h-9 px-3 text-xs" @click="openAction('move', selected, $event.currentTarget)"><i class="bi bi-folder-symlink" aria-hidden="true"></i> Mover</button><button type="button" class="ui-button-secondary min-h-9 px-3 text-xs" @click="openAction('tags', selected, $event.currentTarget)"><i class="bi bi-tags" aria-hidden="true"></i> Tags</button><button type="button" class="ui-button-danger min-h-9 px-3 text-xs" @click="openAction('remove', selected, $event.currentTarget)"><i class="bi bi-trash" aria-hidden="true"></i> Remover</button><button type="button" class="ui-button-ghost min-h-9 px-3 text-xs" @click="selected = []">Limpar seleção</button></div>
                        <div v-if="library.items.length" class="border border-border bg-card">
                            <div class="flex h-11 items-center gap-3 border-b border-border bg-muted/60 px-3"><input type="checkbox" :checked="pageSelected" aria-label="Selecionar página" @change="togglePage" /><span class="text-xs font-semibold uppercase tracking-[0.12em] text-muted-foreground">Selecionar página</span><span class="ml-auto text-xs text-muted-foreground">{{ library.pagination.total }} resultados</span></div>
                            <article v-for="item in library.items" :key="item.publicId" class="grid min-w-0 grid-cols-[20px_88px_minmax(0,1fr)_36px] items-center gap-3 border-b border-border px-3 py-3 last:border-b-0 sm:grid-cols-[20px_112px_minmax(0,1fr)_110px_36px]">
                                <input type="checkbox" :checked="selected.includes(item.publicId)" :aria-label="`Selecionar ${item.title}`" @change="toggleItem(item.publicId)" />
                                <Link :href="item.showUrl" class="aspect-video overflow-hidden bg-muted" :aria-label="`Abrir ${item.title}`"><img v-if="item.thumbnailUrl" :src="item.thumbnailUrl" alt="" loading="lazy" class="h-full w-full object-cover" /><span v-else class="flex h-full items-center justify-center text-[9px] text-muted-foreground">Sem imagem</span></Link>
                                <div class="min-w-0"><h2 class="truncate text-sm font-semibold text-foreground"><Link :href="item.showUrl" class="hover:text-accent">{{ item.title }}</Link></h2><p class="mt-1 truncate text-xs text-muted-foreground">{{ item.channelName || 'Canal não informado' }} · {{ item.languageLabel }} · {{ item.sourceLabel }}</p><div class="mt-1.5 flex min-w-0 items-center gap-1.5 text-[11px] text-muted-foreground"><span v-if="item.folder" class="max-w-32 truncate border border-border px-1.5 py-0.5">{{ item.folder.name }}</span><span v-for="tag in item.tags.slice(0, 2)" :key="tag.publicId" class="max-w-24 truncate bg-muted px-1.5 py-0.5">{{ tag.name }}</span><span v-if="item.tags.length > 2">+{{ item.tags.length - 2 }}</span><span>{{ formatTimestamp(item.durationSeconds * 1000) }}</span></div></div>
                                <time :datetime="item.addedAt" class="hidden text-right text-xs text-muted-foreground sm:block">{{ formatDate(item.addedAt) }}</time>
                                <div data-item-menu class="relative"><button type="button" class="ui-button-ghost size-9 px-0 text-base" :aria-label="`Ações para ${item.title}`" aria-haspopup="menu" :aria-expanded="openMenuId === item.publicId" @click.stop="toggleMenu(item, $event.currentTarget)"><i class="bi bi-three-dots" aria-hidden="true"></i></button><div v-if="openMenuId === item.publicId" :data-menu="item.publicId" role="menu" class="absolute right-0 top-10 z-20 w-52 border border-border bg-card p-1 shadow-[var(--shadow)]"><Link :href="item.showUrl" role="menuitem" class="flex min-h-10 items-center px-3 text-sm hover:bg-muted"><i class="bi bi-box-arrow-up-right mr-2" aria-hidden="true"></i> Abrir</Link><button type="button" role="menuitem" class="flex min-h-10 w-full items-center px-3 text-left text-sm hover:bg-muted" @click="openAction('move', [item.publicId], $event.currentTarget)"><i class="bi bi-folder-symlink mr-2" aria-hidden="true"></i> Mover para pasta</button><button type="button" role="menuitem" class="flex min-h-10 w-full items-center px-3 text-left text-sm hover:bg-muted" @click="openAction('tags', [item.publicId], $event.currentTarget)"><i class="bi bi-tags mr-2" aria-hidden="true"></i> Gerenciar tags</button><button type="button" role="menuitem" class="flex min-h-10 w-full items-center px-3 text-left text-sm text-destructive hover:bg-muted" @click="openAction('remove', [item.publicId], $event.currentTarget)"><i class="bi bi-trash mr-2" aria-hidden="true"></i> Remover da biblioteca</button></div></div>
                            </article>
                        </div>
                        <div v-else class="border border-border bg-card px-6 py-14 text-center"><h2 class="text-xl font-semibold">Nenhuma transcrição encontrada.</h2><p class="mt-2 text-sm text-muted-foreground">Ajuste a busca ou os filtros, ou adicione uma nova transcrição.</p></div>
                        <nav v-if="library.pagination.lastPage > 1" class="mt-6 flex items-center justify-between gap-3" aria-label="Paginação da biblioteca"><Link v-if="library.pagination.previousPageUrl" :href="library.pagination.previousPageUrl" preserve-scroll class="ui-button-secondary" @click="selected = []"><i class="bi bi-arrow-left" aria-hidden="true"></i> Anterior</Link><span v-else></span><p class="text-xs text-muted-foreground">Página {{ library.pagination.currentPage }} de {{ library.pagination.lastPage }}</p><Link v-if="library.pagination.nextPageUrl" :href="library.pagination.nextPageUrl" preserve-scroll class="ui-button-secondary" @click="selected = []">Próxima <i class="bi bi-arrow-right" aria-hidden="true"></i></Link><span v-else></span></nav>
                    </div>
                </div>
            </div>
        </section>
        <dialog ref="organizationDialog" class="ui-dialog m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-md overflow-y-auto p-0" aria-labelledby="organization-title"><div class="flex items-center justify-between border-b border-border p-4"><h2 id="organization-title" class="text-lg font-semibold">Pastas e tags</h2><button type="button" class="ui-button-ghost size-10 px-0" aria-label="Fechar pastas e tags" @click="organizationDialog.close()"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div><div class="p-4"><button type="button" class="flex w-full justify-between py-3" @click="visit({ folder: null }); organizationDialog.close()"><strong>Todas</strong><span>{{ library.counts.all }}</span></button><button type="button" class="flex w-full justify-between py-3" @click="visit({ folder: 'none' }); organizationDialog.close()"><span>Sem pasta</span><span>{{ library.counts.unfiled }}</span></button><h3 class="mt-5 text-xs font-bold uppercase text-muted-foreground"><i class="bi bi-folder mr-1" aria-hidden="true"></i> Pastas</h3><button v-for="folder in library.folders" :key="folder.publicId" type="button" class="flex w-full justify-between border-b border-border py-3 text-left" @click="visit({ folder: folder.publicId }); organizationDialog.close()"><span>{{ folder.name }}</span><span>{{ folder.count }}</span></button><button type="button" class="mt-3 text-sm font-semibold text-accent" @click="organizationDialog.close(); openResource('create-folder', null, $event.currentTarget)"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nova pasta</button><h3 class="mt-7 text-xs font-bold uppercase text-muted-foreground"><i class="bi bi-tag mr-1" aria-hidden="true"></i> Tags</h3><button v-for="tag in library.tags" :key="tag.publicId" type="button" class="block w-full border-b border-border py-3 text-left" @click="visit({ tag: tag.publicId }); organizationDialog.close()">{{ tag.name }}</button><button type="button" class="mt-3 text-sm font-semibold text-accent" @click="organizationDialog.close(); openResource('create-tag', null, $event.currentTarget)"><i class="bi bi-plus-lg" aria-hidden="true"></i> Nova tag</button></div></dialog>
        <dialog ref="resourceDialog" class="ui-dialog m-auto w-[calc(100%-2rem)] max-w-md p-0" aria-labelledby="resource-dialog-title" @close="restoreDialogFocus"><form class="p-6" @submit.prevent="submitResource"><h2 id="resource-dialog-title" class="text-xl font-semibold">{{ resourceLabels[0] }}</h2><template v-if="!resourceMode.startsWith('delete')"><label for="resource-name" class="mt-5 block text-sm font-semibold">{{ resourceLabels[1] }}</label><input id="resource-name" ref="nameInput" v-model="resourceForm.name" maxlength="100" class="ui-input mt-2 text-sm" /><p v-if="resourceForm.errors.name" class="mt-2 text-sm text-destructive">{{ resourceForm.errors.name }}</p></template><p v-else class="mt-4 text-sm leading-6 text-muted-foreground">{{ resourceMode === 'delete-folder' ? 'As transcrições permanecerão na biblioteca e irão para “Sem pasta”.' : 'A tag será removida das transcrições, mas nenhuma transcrição será apagada.' }}</p><div class="mt-6 flex justify-end gap-3"><button type="button" class="ui-button-secondary" :disabled="resourceForm.processing" @click="resourceDialog.close()">Cancelar</button><button type="submit" class="ui-button-primary" :disabled="resourceForm.processing">{{ resourceForm.processing ? 'Salvando...' : resourceLabels[2] }}</button></div></form></dialog>
        <dialog ref="actionDialog" class="ui-dialog m-auto w-[calc(100%-2rem)] max-w-lg p-0" aria-labelledby="action-dialog-title" @close="restoreDialogFocus"><form class="p-6" @submit.prevent="submitAction"><h2 id="action-dialog-title" class="text-xl font-semibold">{{ actionMode === 'move' ? 'Mover para pasta' : actionMode === 'tags' ? 'Gerenciar tags' : `Remover ${actionItemIds.length} ${actionItemIds.length === 1 ? 'transcrição' : 'transcrições'} da biblioteca?` }}</h2><template v-if="actionMode === 'move'"><label for="move-folder" class="mt-5 block text-sm font-semibold">Destino</label><select id="move-folder" v-model="selectedFolder" class="ui-input mt-2 text-sm"><option value="">Sem pasta</option><option v-for="folder in library.folders" :key="folder.publicId" :value="folder.publicId">{{ folder.name }}</option></select></template><template v-else-if="actionMode === 'tags'"><fieldset class="mt-5"><legend class="text-sm font-semibold">Ação</legend><div class="mt-2 flex gap-5"><label class="flex items-center gap-2 text-sm"><input v-model="tagOperation" type="radio" value="add" /> Adicionar tags</label><label class="flex items-center gap-2 text-sm"><input v-model="tagOperation" type="radio" value="remove" /> Remover tags</label></div></fieldset><fieldset class="mt-5 max-h-52 overflow-y-auto border border-border p-3"><legend class="px-1 text-sm font-semibold">Tags</legend><label v-for="tag in library.tags" :key="tag.publicId" class="flex min-h-10 items-center gap-3 text-sm"><input v-model="selectedTags" type="checkbox" :value="tag.publicId" /> {{ tag.name }}</label><p v-if="!library.tags.length" class="text-sm text-muted-foreground">Crie uma tag primeiro.</p></fieldset></template><p v-else class="mt-4 text-sm leading-6 text-muted-foreground">Os transcripts originais não serão apagados.</p><p v-if="actionForm.hasErrors" class="mt-3 text-sm text-destructive">Não foi possível concluir a ação. Revise a seleção.</p><div class="mt-6 flex justify-end gap-3"><button type="button" class="ui-button-secondary" :disabled="actionForm.processing" @click="actionDialog.close()">Cancelar</button><button type="submit" :class="actionMode === 'remove' ? 'ui-button-danger' : 'ui-button-primary'" :disabled="actionForm.processing || (actionMode === 'tags' && selectedTags.length === 0)">{{ actionForm.processing ? 'Processando...' : actionMode === 'remove' ? 'Remover' : 'Aplicar' }}</button></div></form></dialog>
    </PublicLayout>
</template>
