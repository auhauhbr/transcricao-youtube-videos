import axios from 'axios';
import { computed, ref } from 'vue';

export const useTranscriptAnnotations = (initialAnnotations, urls) => {
    const items = ref([...initialAnnotations]);
    const busy = ref(false);
    const error = ref(null);
    const annotationsAt = (startMs) => items.value.filter((annotation) => annotation.startMs === startMs);
    const bookmarkAt = (startMs) => annotationsAt(startMs).find((annotation) => annotation.type === 'bookmark') || null;
    const noteAt = (startMs) => annotationsAt(startMs).find((annotation) => annotation.type === 'note') || null;
    const hasAnnotations = computed(() => items.value.length > 0);

    const request = async (callback, fallbackMessage) => {
        busy.value = true;
        error.value = null;
        try {
            return await callback();
        } catch (requestError) {
            error.value = requestError.response?.data?.message || fallbackMessage;
            return null;
        } finally {
            busy.value = false;
        }
    };
    const add = async (payload) => request(async () => {
        const response = await axios.post(urls.store, payload, { headers: { Accept: 'application/json' } });
        items.value = [...items.value, response.data.annotation].sort((left, right) => left.startMs - right.startMs || left.type.localeCompare(right.type));
        return response.data.annotation;
    }, 'Não foi possível salvar a annotation.');
    const remove = async (annotation) => request(async () => {
        await axios.delete(annotation.urls.destroy, { headers: { Accept: 'application/json' } });
        items.value = items.value.filter((item) => item.publicId !== annotation.publicId);
        return true;
    }, 'Não foi possível remover a annotation.');
    const toggleBookmark = async (startMs) => {
        const bookmark = bookmarkAt(startMs);
        return bookmark ? remove(bookmark) : add({ start_ms: startMs, type: 'bookmark' });
    };
    const saveNote = async (startMs, text, annotation = null) => {
        if (!annotation) return add({ start_ms: startMs, type: 'note', text });

        return request(async () => {
            const response = await axios.patch(annotation.urls.update, { text }, { headers: { Accept: 'application/json' } });
            items.value = items.value.map((item) => item.publicId === annotation.publicId ? response.data.annotation : item);
            return response.data.annotation;
        }, 'Não foi possível atualizar a nota.');
    };

    return { items, busy, error, hasAnnotations, annotationsAt, bookmarkAt, noteAt, toggleBookmark, saveNote, remove, clearError: () => { error.value = null; } };
};
