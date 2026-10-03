<script setup>
defineProps({
    startMs: { type: Number, required: true },
    bookmarked: { type: Boolean, required: true },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle-bookmark']);
</script>

<template>
    <div class="flex shrink-0 items-center gap-1" @click.stop>
        <button
            type="button"
            class="ui-button-ghost size-8 px-0"
            :disabled="disabled"
            :aria-pressed="bookmarked"
            :aria-label="bookmarked ? 'Remover marcador deste trecho' : 'Adicionar marcador neste trecho'"
            :title="bookmarked ? 'Remover marcador' : 'Adicionar marcador'"
            @click="emit('toggle-bookmark', startMs)"
        >
            <i :class="['bi', bookmarked ? 'bi-bookmark-fill text-accent' : 'bi-bookmark']" aria-hidden="true"></i>
        </button>
    </div>
</template>
