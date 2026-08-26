<script setup>
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { onBeforeUnmount, watch } from 'vue';
import { documentEditorExtensions } from '../utils/documentEditorExtensions.js';

const props = defineProps({ content: { type: Object, required: true } });
const editor = useEditor({
    content: props.content,
    editable: false,
    extensions: documentEditorExtensions(),
    editorProps: {
        attributes: {
            class: 'document-editor-content revision-preview-content',
            role: 'document',
            'aria-label': 'Conteúdo da versão selecionada, somente leitura',
        },
    },
});

watch(() => props.content, (value) => {
    editor.value?.commands.setContent(value, { emitUpdate: false });
}, { deep: true });

onBeforeUnmount(() => editor.value?.destroy());
</script>

<template>
    <div class="border border-border bg-card">
        <EditorContent :editor="editor" />
    </div>
</template>
