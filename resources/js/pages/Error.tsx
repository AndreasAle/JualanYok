import MarketingLayout from '@/layouts/MarketingLayout';
import { ButtonLink, Card } from '@/components/ui';

const COPY: Record<number, { title: string; message: string }> = {
    403: { title: 'Nggak boleh masuk sini', message: 'Kamu nggak punya akses ke halaman ini. Kalau merasa ini keliru, hubungi pemilik akun atau tim support.' },
    404: { title: 'Halamannya nggak ketemu', message: 'Mungkin linknya salah ketik, atau tokonya udah nggak aktif.' },
    419: { title: 'Sesinya kedaluwarsa', message: 'Halaman ini kelamaan dibuka. Muat ulang lalu coba lagi ya.' },
    429: { title: 'Kebanyakan percobaan', message: 'Tunggu sebentar, lalu coba lagi.' },
    500: { title: 'Ada yang error di sisi kami', message: 'Kami sudah mencatat masalahnya. Coba lagi beberapa saat lagi.' },
    503: { title: 'Lagi maintenance', message: 'Kami lagi benerin sesuatu. Sebentar lagi balik normal.' },
};

export default function Error({ status, message }: { status: number; message?: string }) {
    const copy = COPY[status] ?? {
        title: 'Ada yang nggak beres',
        message: message || 'Coba muat ulang halamannya.',
    };

    return (
        <MarketingLayout title={`${status}`}>
            <section className="mx-auto grid max-w-2xl place-items-center px-4 py-24 sm:px-6">
                <Card className="w-full p-10 text-center">
                    <p className="text-6xl font-black gradient-text">{status}</p>
                    <h1 className="mt-4 text-2xl font-extrabold tracking-tight">{copy.title}</h1>
                    <p className="mx-auto mt-2 max-w-sm text-sm text-muted">{copy.message}</p>

                    <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                        <ButtonLink href="/" variant="gradient">
                            Balik ke Beranda
                        </ButtonLink>
                        <ButtonLink href="/contact" variant="outline">
                            Hubungi Support
                        </ButtonLink>
                    </div>
                </Card>
            </section>
        </MarketingLayout>
    );
}
