import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { StrictMode } from 'react';
import { Toaster } from '@/components/ui/toaster';

const appName = 'JualanYok';

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),

    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.tsx', { eager: true });
        const page = pages[`./pages/${name}.tsx`] as { default: any } | undefined;

        if (!page) {
            throw new Error(`Halaman "${name}" tidak ditemukan di resources/js/pages.`);
        }

        return page.default;
    },

    setup({ el, App, props }) {
        createRoot(el).render(
            <StrictMode>
                {/*
                  Toaster reads flash messages with usePage(), so it has to live
                  inside <App> — rendering it as a sibling throws and takes the
                  whole tree down with it.
                */}
                <App {...props}>
                    {({ Component, props: pageProps, key }) => (
                        <>
                            <Component key={key} {...pageProps} />
                            <Toaster />
                        </>
                    )}
                </App>
            </StrictMode>,
        );
    },

    progress: {
        color: '#7C3AED',
        showSpinner: false,
    },
});
