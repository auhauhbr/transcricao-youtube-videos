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
        class="ui-dialog m-auto w-[calc(100%-2rem)] max-w-md p-0"
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
                    class="ui-button-secondary"
                    :disabled="removing"
                    @click="close"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    class="ui-button-danger"
                    :disabled="removing"
                    @click="remove"
                >
                    <i class="bi bi-trash" aria-hidden="true"></i> {{ removing ? 'Removendo...' : 'Remover' }}
                </button>
            </div>
        </div>
    </dialog>
</template>
