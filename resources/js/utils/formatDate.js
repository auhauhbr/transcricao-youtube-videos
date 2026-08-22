export const formatDate = (value) => {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'medium',
    }).format(date);
};
