<script setup>
import { router } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, ref } from 'vue';

const emit = defineEmits(['removed']);
const dialog = ref(null);
const cancelButton = ref(null);
const selectedItem = ref(null);
const removing = ref(false);
let returnFocus = null;

const open = async (item, trigger = document.activeElement) => {
    if (!dialog.value || dialog.value.open) {
        return;
    }

    selectedItem.value = item;
    returnFocus = trigger instanceof HTMLElement ? trigger : null;
    dialog.value.showModal();
    await nextTick();
    cancelButton.value?.focus();
};

const close = () => {
    if (!removing.value && dialog.value?.open) {
        dialog.value.close();
    }
};

const remove = () => {
    if (!selectedItem.value || removing.value) {
        return;
    }

    removing.value = true;
    router.delete(selectedItem.value.destroyUrl, {
        preserveScroll: true,
        onSuccess: () => {
            dialog.value?.close();
            emit('removed');
        },
        onFinish: () => {
            removing.value = false;
        },
    });
};

const restoreFocus = () => {
    if (returnFocus?.isConnected) {
        returnFocus.focus();
    }

    selectedItem.value = null;
};

onBeforeUnmount(() => {
    if (dialog.value?.open) {
        dialog.value.close();
    }
});

defineExpose({ open });
</script>

<template>
    <dialog
        ref="dialog"
        class="m-auto w-[calc(100%-2rem)] max-w-md border border-border bg-card p-0 text-foreground shadow-2xl backdrop:bg-black/70 backdrop:backdrop-blur-sm"
        aria-labelledby="remove-library-title"
        aria-describedby="remove-library-description"
        @close="restoreFocus"
    >
        <div class="p-6 sm:p-7">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-destructive">Remover item</p>
            <h2 id="remove-library-title" class="mt-2 text-xl font-semibold tracking-tight">Remover da biblioteca?</h2>
            <p id="remove-library-description" class="mt-4 text-sm leading-6 text-muted-foreground">
                A transcrição será removida da sua biblioteca. O conteúdo original não será apagado.
            </p>

            <div class="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button
                    ref="cancelButton"
                    type="button"
                    class="inline-flex h-11 items-center justify-center border border-border bg-background px-5 text-sm font-semibold text-foreground transition-colors hover:border-accent hover:text-accent"
                    :disabled="removing"
                    @click="close"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    class="inline-flex h-11 items-center justify-center bg-red-700 px-5 text-sm font-semibold text-white transition-colors hover:bg-red-800 disabled:cursor-wait disabled:opacity-70"
                    :disabled="removing"
                    @click="remove"
                >
                    {{ removing ? 'Removendo...' : 'Remover' }}
                </button>
            </div>
        </div>
    </dialog>
</template>
