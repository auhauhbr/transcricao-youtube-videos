let apiPromise = null;

const API_URL = 'https://www.youtube.com/iframe_api';
const LOAD_TIMEOUT_MS = 15_000;

export const loadYouTubeIframeApi = () => {
    if (typeof window === 'undefined') {
        return Promise.reject(new Error('The YouTube Player API requires a browser.'));
    }

    if (window.YT?.Player) {
        return Promise.resolve(window.YT);
    }

    if (apiPromise) {
        return apiPromise;
    }

    apiPromise = new Promise((resolve, reject) => {
        const previousReadyHandler = window.onYouTubeIframeAPIReady;
        let settled = false;

        const finish = (callback) => {
            if (settled) {
                return;
            }

            settled = true;
            window.clearTimeout(timeout);
            callback();
        };

        const fail = () => {
            finish(() => {
                window.onYouTubeIframeAPIReady = previousReadyHandler ?? null;
                apiPromise = null;
                reject(new Error('The YouTube Player API could not be loaded.'));
            });
        };

        window.onYouTubeIframeAPIReady = () => {
            if (typeof previousReadyHandler === 'function') {
                previousReadyHandler();
            }

            finish(() => {
                window.onYouTubeIframeAPIReady = previousReadyHandler ?? null;

                if (window.YT?.Player) {
                    resolve(window.YT);
                    return;
                }

                apiPromise = null;
                reject(new Error('The YouTube Player API initialized without a player constructor.'));
            });
        };

        const timeout = window.setTimeout(fail, LOAD_TIMEOUT_MS);
        let script = document.querySelector(`script[src="${API_URL}"]`);

        if (!script) {
            script = document.createElement('script');
            script.src = API_URL;
            script.async = true;
            document.head.appendChild(script);
        }

        script.addEventListener('error', fail, { once: true });
    });

    return apiPromise;
};
