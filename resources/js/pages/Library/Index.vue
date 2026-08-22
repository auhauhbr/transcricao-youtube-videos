<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import RemoveLibraryItemDialog from '../../components/RemoveLibraryItemDialog.vue';
import PublicLayout from '../../layouts/PublicLayout.vue';
import { formatDate } from '../../utils/formatDate.js';
import { formatTimestamp } from '../../utils/formatTimestamp.js';

defineProps({
    appName: { type: String, required: true },
    flash: { type: Object, required: true },
    library: { type: Object, required: true },
});

const removeDialog = ref(null);
const title = ref(null);

const requestRemoval = (item, event) => {
    removeDialog.value?.open(item, event.currentTarget);
};

const focusTitleAfterRemoval = async () => {
    await nextTick();
    title.value?.focus();
};
</script>

<template>
    <Head title="Biblioteca">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <PublicLayout :app-name="appName">
        <section class="flex-1 border-b border-border bg-background">
            <div class="mx-auto max-w-7xl px-5 py-10 sm:px-8 sm:py-14 lg:px-10">
                <header class="flex flex-col gap-5 border-b border-border pb-7 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-accent">Sua coleção</p>
                        <h1 ref="title" class="mt-3 text-3xl font-semibold tracking-tight text-foreground sm:text-4xl" tabindex="-1">Biblioteca</h1>
                        <p class="mt-3 text-sm text-muted-foreground">
                            {{ library.pagination.total === 1 ? '1 transcrição salva' : `${library.pagination.total} transcrições salvas` }}
                        </p>
                    </div>
                    <Link href="/" class="inline-flex h-11 items-center justify-center bg-action px-5 text-sm font-semibold text-action-foreground transition-colors hover:bg-action-hover">
                        Nova transcrição
                    </Link>
                </header>

                <p
                    v-if="flash.status === 'library-item-removed'"
                    class="mt-6 border border-border bg-card px-4 py-3 text-sm text-muted-foreground"
                    aria-live="polite"
                >
                    Transcrição removida da biblioteca.
                </p>

                <div v-if="library.items.length" class="mt-7 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <article v-for="item in library.items" :key="item.publicId" class="flex min-w-0 flex-col border border-border bg-card">
                        <Link :href="item.showUrl" class="block border-b border-border bg-muted" :aria-label="`Abrir ${item.title}`">
                            <div class="aspect-video overflow-hidden">
                                <img
                                    v-if="item.thumbnailUrl"
                                    :src="item.thumbnailUrl"
                                    alt=""
                                    loading="lazy"
                                    class="h-full w-full object-cover transition-transform duration-200 hover:scale-[1.02]"
                                />
                                <div v-else class="flex h-full items-center justify-center text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">
                                    Sem thumbnail
                                </div>
                            </div>
                        </Link>

                        <div class="flex flex-1 flex-col p-5">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-accent">
                                {{ item.languageName || item.languageCode }} · {{ item.sourceLabel }}
                            </p>
                            <h2 class="mt-2 line-clamp-2 text-lg font-semibold leading-6 tracking-tight text-foreground">
                                <Link :href="item.showUrl" class="hover:text-accent">{{ item.title }}</Link>
                            </h2>
                            <p v-if="item.channelName" class="mt-2 truncate text-sm text-muted-foreground">{{ item.channelName }}</p>

                            <dl class="mt-5 grid grid-cols-2 gap-3 border-t border-border pt-4 text-xs">
                                <div>
                                    <dt class="text-muted-foreground">Duração</dt>
                                    <dd class="mt-1 font-medium text-foreground">{{ formatTimestamp(item.durationSeconds * 1000) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">Adicionada</dt>
                                    <dd class="mt-1 font-medium text-foreground">{{ formatDate(item.addedAt) }}</dd>
                                </div>
                            </dl>

                            <div class="mt-5 flex items-center gap-2">
                                <Link :href="item.showUrl" class="inline-flex h-10 flex-1 items-center justify-center bg-action px-4 text-sm font-semibold text-action-foreground hover:bg-action-hover">
                                    Abrir
                                </Link>
                                <button
                                    type="button"
                                    class="inline-flex h-10 items-center justify-center border border-border bg-background px-4 text-sm font-semibold text-foreground transition-colors hover:border-destructive hover:text-destructive"
                                    @click="requestRemoval(item, $event)"
                                >
                                    Remover
                                </button>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-else class="mt-8 border border-border bg-card px-6 py-14 text-center sm:px-10">
                    <h2 class="text-2xl font-semibold tracking-tight text-foreground">Sua biblioteca ainda está vazia.</h2>
                    <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-muted-foreground">Faça uma transcrição para vê-la aqui automaticamente.</p>
                    <Link href="/" class="mt-7 inline-flex h-11 items-center bg-action px-5 text-sm font-semibold text-action-foreground hover:bg-action-hover">
                        Nova transcrição
                    </Link>
                </div>

                <nav v-if="library.pagination.lastPage > 1" class="mt-8 flex items-center justify-between gap-4 border-t border-border pt-6" aria-label="Paginação da biblioteca">
                    <Link
                        v-if="library.pagination.previousPageUrl"
                        :href="library.pagination.previousPageUrl"
                        preserve-scroll
                        class="inline-flex h-10 items-center border border-border bg-card px-4 text-sm font-semibold text-foreground hover:border-accent hover:text-accent"
                    >
                        ← Anterior
                    </Link>
                    <span v-else class="h-10 w-24" aria-hidden="true"></span>
                    <p class="text-sm text-muted-foreground">Página {{ library.pagination.currentPage }} de {{ library.pagination.lastPage }}</p>
                    <Link
                        v-if="library.pagination.nextPageUrl"
                        :href="library.pagination.nextPageUrl"
                        preserve-scroll
                        class="inline-flex h-10 items-center border border-border bg-card px-4 text-sm font-semibold text-foreground hover:border-accent hover:text-accent"
                    >
                        Próxima →
                    </Link>
                    <span v-else class="h-10 w-24" aria-hidden="true"></span>
                </nav>
            </div>
        </section>

        <RemoveLibraryItemDialog ref="removeDialog" @removed="focusTitleAfterRemoval" />
    </PublicLayout>
</template>
