/**
 * Normalises a share URL into one that is actually allowed inside an iframe.
 *
 * Providers refuse to render their public "watch" pages in a frame
 * (X-Frame-Options), so pasting a normal YouTube link produces an empty grey
 * box. Each provider has a dedicated player URL, and this maps to it.
 *
 * Returns null when the link is not from a supported provider, so callers can
 * show a helpful message instead of a broken frame.
 */
export interface EmbedTarget {
    url: string;
    provider: 'youtube' | 'vimeo' | 'spotify' | 'maps';
    /** Player aspect ratio; Spotify players are short and wide. */
    aspect: 'video' | 'audio';
}

export function toEmbedUrl(raw: string): EmbedTarget | null {
    const input = (raw ?? '').trim();

    if (input === '') {
        return null;
    }

    let url: URL;

    try {
        url = new URL(input.startsWith('http') ? input : `https://${input}`);
    } catch {
        return null;
    }

    const host = url.hostname.replace(/^www\./, '');

    /* YouTube — watch, short link, shorts, live and already-embedded URLs. */
    if (host === 'youtube.com' || host === 'm.youtube.com' || host === 'youtube-nocookie.com') {
        const id =
            url.searchParams.get('v') ??
            url.pathname.match(/\/(?:embed|shorts|live|v)\/([A-Za-z0-9_-]{11})/)?.[1];

        if (id) {
            const start = url.searchParams.get('t') ?? url.searchParams.get('start');
            const suffix = start ? `?start=${parseInt(start, 10) || 0}` : '';

            return { url: `https://www.youtube-nocookie.com/embed/${id}${suffix}`, provider: 'youtube', aspect: 'video' };
        }

        return null;
    }

    if (host === 'youtu.be') {
        const id = url.pathname.slice(1).match(/^([A-Za-z0-9_-]{11})/)?.[1];

        return id
            ? { url: `https://www.youtube-nocookie.com/embed/${id}`, provider: 'youtube', aspect: 'video' }
            : null;
    }

    /* Vimeo */
    if (host === 'vimeo.com' || host === 'player.vimeo.com') {
        const id = url.pathname.match(/(\d{6,})/)?.[1];

        return id
            ? { url: `https://player.vimeo.com/video/${id}`, provider: 'vimeo', aspect: 'video' }
            : null;
    }

    /* Spotify — track, album, playlist, episode, show. */
    if (host === 'open.spotify.com' || host === 'spotify.com') {
        const match = url.pathname.match(/\/(track|album|playlist|episode|show|artist)\/([A-Za-z0-9]+)/);

        return match
            ? { url: `https://open.spotify.com/embed/${match[1]}/${match[2]}`, provider: 'spotify', aspect: 'audio' }
            : null;
    }

    /* Google Maps — only the dedicated embed endpoint is framable. */
    if (host === 'google.com' || host.endsWith('.google.com')) {
        if (url.pathname.startsWith('/maps/embed')) {
            return { url: url.toString(), provider: 'maps', aspect: 'video' };
        }

        return null;
    }

    return null;
}

/** Human-readable list for form hints and error messages. */
export const EMBED_PROVIDERS = 'YouTube, Vimeo, Spotify, atau Google Maps (embed)';
