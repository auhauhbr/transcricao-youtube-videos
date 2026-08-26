<script setup>
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';

defineProps({
    downloadUrl: {
        type: String,
        required: true,
    },
});

const dialog = ref(null);
const trigger = ref(null);
const formatSelect = ref(null);
const format = ref('txt');
const mode = ref('formatted');
const timestamps = ref(true);
const modeDescription = computed(() =>
    mode.value === 'formatted' ? 'Parágrafos agrupados para leitura.' : 'Preserva os segmentos originais da legenda.',
);

const openDialog = async () => {
    if (!dialog.value || dialog.value.open) {
        return;
    }

    dialog.value.showModal();
    await nextTick();
    formatSelect.value?.focus();
};

const closeDialog = () => {
    if (dialog.value?.open) {
        dialog.value.close();
    }
};

const restoreTriggerFocus = () => {
    trigger.value?.focus();
};

onBeforeUnmount(() => {
    if (dialog.value?.open) {
        dialog.value.close();
    }
});
</script>

<template>
    <button
        ref="trigger"
        type="button"
        class="ui-button-secondary"
        @click="openDialog"
    >
        <i class="bi bi-download" aria-hidden="true"></i> Baixar
    </button>

    <dialog
        ref="dialog"
        class="ui-dialog m-auto w-[calc(100%-2rem)] max-w-md p-0"
        aria-labelledby="download-dialog-title"
        aria-describedby="download-dialog-description"
        @close="restoreTriggerFocus"
    >
        <form :action="downloadUrl" method="get" class="p-6 sm:p-7" @submit="closeDialog">
            <div class="flex items-start justify-between gap-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-accent">Exportar arquivo</p>
                    <h2 id="download-dialog-title" class="mt-2 text-xl font-semibold tracking-tight">Baixar transcrição</h2>
                    <p id="download-dialog-description" class="mt-2 text-sm leading-6 text-muted-foreground">
                        Escolha o formato e a organização do conteúdo.
                    </p>
                </div>
                <button
                    type="button"
                    class="ui-button-ghost size-10 shrink-0 px-0 text-base"
                    aria-label="Fechar modal de download"
                    @click="closeDialog"
                >
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>

            <div class="mt-6 space-y-5">
                <div>
                    <label for="download-format" class="text-sm font-semibold text-foreground">Formato</label>
                    <select
                        id="download-format"
                        ref="formatSelect"
                        v-model="format"
                        name="format"
                        class="ui-input mt-2 text-sm"
                    >
                        <option value="txt">TXT</option>
                        <option value="md">Markdown</option>
                    </select>
                </div>

                <div>
                    <label for="download-mode" class="text-sm font-semibold text-foreground">Organização</label>
                    <select
                        id="download-mode"
                        v-model="mode"
                        name="mode"
                        class="ui-input mt-2 text-sm"
                    >
                        <option value="formatted">Formatado</option>
                        <option value="segmented">Segmentado</option>
                    </select>
                    <p class="mt-2 text-xs leading-5 text-muted-foreground">{{ modeDescription }}</p>
                </div>

                <label class="flex min-h-11 cursor-pointer items-center gap-3 border border-border bg-background px-4 py-3 text-sm font-medium text-foreground">
                    <input type="hidden" name="timestamps" value="0" />
                    <input
                        v-model="timestamps"
                        type="checkbox"
                        name="timestamps"
                        value="1"
                        class="size-4 accent-[var(--color-accent)]"
                    />
                    Incluir timestamps
                </label>
            </div>

            <div class="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="ui-button-secondary"
                    @click="closeDialog"
                >
                    Cancelar
                </button>
                <button
                    type="submit"
                    class="ui-button-primary"
                >
                    <i class="bi bi-download" aria-hidden="true"></i> Baixar transcrição
                </button>
            </div>
        </form>
    </dialog>
</template>
