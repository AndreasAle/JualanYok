/**
 * Video helpers that run before an upload leaves the browser.
 *
 * Both of these exist for the same reason: a clip is the most expensive thing
 * a shop can put on a page, and the cheapest place to deal with that is here,
 * before the bytes have been sent anywhere.
 */

/** Nothing longer than this is a product video; it is a home movie. */
export const MAX_SECONDS = 90;

interface Probe {
    seconds: number;
    width: number;
    height: number;
}

function loadMetadata(file: File): Promise<{ video: HTMLVideoElement; url: string; probe: Probe }> {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const video = document.createElement('video');

        video.preload = 'metadata';
        video.muted = true;
        // Required on iOS, where a video that cannot play inline never
        // produces a frame to capture.
        video.playsInline = true;
        video.src = url;

        video.onloadedmetadata = () =>
            resolve({
                video,
                url,
                probe: {
                    seconds: Number.isFinite(video.duration) ? video.duration : 0,
                    width: video.videoWidth,
                    height: video.videoHeight,
                },
            });

        video.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('Video tidak bisa dibaca.'));
        };
    });
}

/** How long a clip runs, so an over-long one is refused before it uploads. */
export async function videoDuration(file: File): Promise<number> {
    try {
        const { video, url, probe } = await loadMetadata(file);
        video.src = '';
        URL.revokeObjectURL(url);

        return probe.seconds;
    } catch {
        // A clip we cannot measure is not a clip we should reject: browsers
        // disagree about codecs, and the server still enforces the size cap.
        return 0;
    }
}

/**
 * A still from one second in, as a small JPEG.
 *
 * This is the whole bandwidth argument. Without a poster the browser has to
 * fetch part of every video just to draw the gallery — for every visitor,
 * including the overwhelming majority who never press play. With one, the page
 * costs a few kilobytes and the video is fetched only by people who asked for
 * it.
 */
export async function capturePoster(file: File): Promise<File | null> {
    try {
        const { video, url, probe } = await loadMetadata(file);

        const frame = await new Promise<Blob | null>((resolve) => {
            const give = () => resolve(null);
            const timeout = window.setTimeout(give, 4000);

            video.onseeked = () => {
                window.clearTimeout(timeout);

                // Capped so a 4K clip does not produce a poster heavier than
                // the photos beside it.
                const scale = Math.min(1, 1080 / Math.max(probe.width || 1, probe.height || 1));
                const canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round((probe.width || 720) * scale));
                canvas.height = Math.max(1, Math.round((probe.height || 1280) * scale));

                const context = canvas.getContext('2d');

                if (!context) {
                    give();

                    return;
                }

                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                canvas.toBlob((blob) => resolve(blob), 'image/jpeg', 0.72);
            };

            video.onerror = give;

            // One second in: frame zero of a phone video is often a blur or a
            // black frame while the exposure settles.
            video.currentTime = Math.min(1, Math.max(0, probe.seconds - 0.1));
        });

        video.src = '';
        URL.revokeObjectURL(url);

        return frame ? new File([frame], 'poster.jpg', { type: 'image/jpeg' }) : null;
    } catch {
        // No poster is a smaller problem than a failed upload; the gallery
        // falls back to a neutral tile.
        return null;
    }
}
