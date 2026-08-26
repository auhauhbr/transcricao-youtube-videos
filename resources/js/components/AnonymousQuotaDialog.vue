<script setup>
import { Link } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, ref } from 'vue';

defineProps({
    message: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['close']);
const dialog = ref(null);
const loginLink = ref(null);
let returnFocus = null;

const open = async (trigger = document.activeElement) => {
    if (!dialog.value || dialog.value.open) {
        return;
    }

    returnFocus = trigger instanceof HTMLElement ? trigger : null;
    dialog.value.showModal();
    await nextTick();
    loginLink.value?.$el?.focus();
};

const close = () => {
    if (dialog.value?.open) {
        dialog.value.close();
    }
};

const handleClose = () => {
    emit('close');
    returnFocus?.focus();
};

onBeforeUnmount(close);

defineExpose({ open });
</script>

<template>
    <dialog
        ref="dialog"
        class="ui-dialog m-auto w-[calc(100%-2rem)] max-w-md p-0"
        aria-labelledby="quota-dialog-title"
        aria-describedby="quota-dialog-description"
        @close="handleClose"
    >
        <div class="p-6 sm:p-7">
            <div class="flex items-start justify-between gap-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-accent">Limite gratuito</p>
                    <h2 id="quota-dialog-title" class="mt-2 text-xl font-semibold tracking-tight">Continue com uma conta</h2>
                </div>
                <button
                    type="button"
                    class="ui-button-ghost size-10 shrink-0 px-0 text-base"
                    aria-label="Fechar aviso de limite gratuito"
                    @click="close"
                >
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>

            <p id="quota-dialog-description" class="mt-5 text-sm leading-6 text-muted-foreground">{{ message }}</p>

            <div class="mt-7 flex flex-col gap-3 sm:flex-row sm:justify-end">
                <Link
                    ref="loginLink"
                    href="/login"
                    class="ui-button-secondary"
                >
                    Entrar
                </Link>
                <Link
                    href="/register"
                    class="ui-button-primary"
                >
                    Criar conta
                </Link>
            </div>
        </div>
    </dialog>
</template>
