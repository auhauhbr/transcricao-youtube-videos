export const formatTimestamp = (milliseconds) => {
    const numericValue = Number(milliseconds);
    const totalSeconds = Number.isFinite(numericValue) ? Math.max(0, Math.floor(numericValue / 1000)) : 0;
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    const paddedMinutes = String(minutes).padStart(2, '0');
    const paddedSeconds = String(seconds).padStart(2, '0');

    return hours > 0 ? `${hours}:${paddedMinutes}:${paddedSeconds}` : `${paddedMinutes}:${paddedSeconds}`;
};
