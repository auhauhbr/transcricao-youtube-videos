<script setup>
const props = defineProps({ startMs: { type: Number, required: true }, bookmarked: { type: Boolean, required: true }, hasNote: { type: Boolean, required: true }, disabled: { type: Boolean, default: false } });
const emit = defineEmits(['toggle-bookmark', 'edit-note']);
</script>

<template>
    <div class="flex shrink-0 items-center gap-1" @click.stop>
        <button type="button" class="ui-button-ghost size-8 px-0" :disabled="disabled" :aria-pressed="bookmarked" :aria-label="bookmarked ? 'Remover marcador deste trecho' : 'Adicionar marcador neste trecho'" :title="bookmarked ? 'Remover marcador' : 'Adicionar marcador'" @click="emit('toggle-bookmark', startMs)">
            <i :class="['bi', bookmarked ? 'bi-bookmark-fill text-accent' : 'bi-bookmark']" aria-hidden="true"></i>
        </button>
        <button type="button" class="ui-button-ghost relative size-8 px-0" :disabled="disabled" :aria-label="hasNote ? 'Editar nota deste trecho' : 'Adicionar nota neste trecho'" :title="hasNote ? 'Editar nota' : 'Adicionar nota'" @click="emit('edit-note', startMs)">
            <i :class="['bi', hasNote ? 'bi-journal-text text-accent' : 'bi-journal-plus']" aria-hidden="true"></i>
            <span v-if="hasNote" class="sr-only">Há uma nota neste trecho</span>
        </button>
    </div>
</template>
