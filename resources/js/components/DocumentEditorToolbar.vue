<script setup>
defineProps({ editor: { type: Object, default: null } });

const actions = [
    { label: 'Parágrafo', icon: 'bi-text-paragraph', command: (editor) => editor.chain().focus().setParagraph().run(), active: (editor) => editor.isActive('paragraph') },
    { label: 'Título 2', icon: 'bi-type-h2', command: (editor) => editor.chain().focus().toggleHeading({ level: 2 }).run(), active: (editor) => editor.isActive('heading', { level: 2 }) },
    { label: 'Título 3', icon: 'bi-type-h3', command: (editor) => editor.chain().focus().toggleHeading({ level: 3 }).run(), active: (editor) => editor.isActive('heading', { level: 3 }) },
    { label: 'Negrito', icon: 'bi-type-bold', command: (editor) => editor.chain().focus().toggleBold().run(), active: (editor) => editor.isActive('bold') },
    { label: 'Itálico', icon: 'bi-type-italic', command: (editor) => editor.chain().focus().toggleItalic().run(), active: (editor) => editor.isActive('italic') },
    { label: 'Lista com marcadores', icon: 'bi-list-ul', command: (editor) => editor.chain().focus().toggleBulletList().run(), active: (editor) => editor.isActive('bulletList') },
    { label: 'Lista numerada', icon: 'bi-list-ol', command: (editor) => editor.chain().focus().toggleOrderedList().run(), active: (editor) => editor.isActive('orderedList') },
    { label: 'Citação', icon: 'bi-blockquote-left', command: (editor) => editor.chain().focus().toggleBlockquote().run(), active: (editor) => editor.isActive('blockquote') },
];
</script>

<template>
    <div class="flex min-w-0 flex-wrap gap-1 border-b border-border bg-muted/60 p-2" role="toolbar" aria-label="Formatação do documento">
        <button
            v-for="action in actions"
            :key="action.label"
            type="button"
            class="ui-button-ghost size-9 shrink-0 px-0 text-base"
            :class="editor && action.active(editor) ? 'bg-card text-accent' : ''"
            :aria-label="action.label"
            :title="action.label"
            :aria-pressed="editor ? action.active(editor) : false"
            :disabled="!editor"
            @click="action.command(editor)"
        >
            <i :class="['bi', action.icon]" aria-hidden="true"></i>
        </button>
        <span class="mx-1 h-9 border-l border-border" aria-hidden="true"></span>
        <button type="button" class="ui-button-ghost size-9 shrink-0 px-0 text-base" aria-label="Desfazer" title="Desfazer" :disabled="!editor || !editor.can().chain().focus().undo().run()" @click="editor.chain().focus().undo().run()">
            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
        </button>
        <button type="button" class="ui-button-ghost size-9 shrink-0 px-0 text-base" aria-label="Refazer" title="Refazer" :disabled="!editor || !editor.can().chain().focus().redo().run()" @click="editor.chain().focus().redo().run()">
            <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
        </button>
    </div>
</template>
