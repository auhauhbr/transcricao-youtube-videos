<script setup>
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { onBeforeUnmount } from 'vue';
import DocumentEditorToolbar from './DocumentEditorToolbar.vue';

const props = defineProps({ content: { type: Object, required: true } });
const emit = defineEmits(['update:content']);
const editor = useEditor({
    content: props.content,
    extensions: [StarterKit.configure({
        heading: { levels: [2, 3] },
        code: false,
        codeBlock: false,
        horizontalRule: false,
        link: false,
        strike: false,
        underline: false,
        trailingNode: false,
    })],
    editorProps: {
        attributes: {
            class: 'document-editor-content',
            'aria-label': 'Conteúdo editável do documento',
        },
    },
    onUpdate: ({ editor: currentEditor }) => emit('update:content', currentEditor.getJSON()),
});

onBeforeUnmount(() => editor.value?.destroy());
</script>

<template>
    <div class="min-w-0 border border-border bg-card">
        <DocumentEditorToolbar :editor="editor" />
        <EditorContent :editor="editor" />
    </div>
</template>
