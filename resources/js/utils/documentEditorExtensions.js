import StarterKit from '@tiptap/starter-kit';

export const documentEditorExtensions = () => [StarterKit.configure({
    heading: { levels: [2, 3] },
    code: false,
    codeBlock: false,
    horizontalRule: false,
    link: false,
    strike: false,
    underline: false,
    trailingNode: false,
})];
