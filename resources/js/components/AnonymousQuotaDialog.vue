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
        class="m-auto w-[calc(100%-2rem)] max-w-md border border-border bg-card p-0 text-foreground shadow-2xl backdrop:bg-black/70 backdrop:backdrop-blur-sm"
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
                    class="inline-flex size-10 shrink-0 items-center justify-center border border-border text-xl leading-none text-muted-foreground transition-colors hover:border-accent hover:text-accent"
                    aria-label="Fechar aviso de limite gratuito"
                    @click="close"
                >
                    <span aria-hidden="true">×</span>
                </button>
            </div>

            <p id="quota-dialog-description" class="mt-5 text-sm leading-6 text-muted-foreground">{{ message }}</p>

            <div class="mt-7 flex flex-col gap-3 sm:flex-row sm:justify-end">
                <Link
                    ref="loginLink"
                    href="/login"
                    class="inline-flex h-11 items-center justify-center border border-border bg-background px-5 text-sm font-semibold text-foreground transition-colors hover:border-accent hover:text-accent"
                >
                    Entrar
                </Link>
                <Link
                    href="/register"
                    class="inline-flex h-11 items-center justify-center bg-red-700 px-5 text-sm font-semibold text-white transition-colors hover:bg-red-800"
                >
                    Criar conta
                </Link>
            </div>
        </div>
    </dialog>
</template>
