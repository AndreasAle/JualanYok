import MarketingLayout from '@/layouts/MarketingLayout';
import { Card } from '@/components/ui';

export default function StaticPage({
    page,
}: {
    page: { slug: string; title: string; body: string; seo_description: string | null };
}) {
    return (
        <MarketingLayout title={page.title} description={page.seo_description ?? undefined}>
            <article className="mx-auto max-w-3xl px-4 py-14 sm:px-6 lg:py-20">
                <h1 className="text-3xl font-extrabold tracking-tight text-balance sm:text-4xl">{page.title}</h1>

                <Card className="mt-8 p-6 sm:p-10">
                    {/*
                      Static page bodies are authored by platform admins only and
                      stored as plain paragraphs, so they render as text.
                    */}
                    <div className="space-y-4 text-sm leading-relaxed text-muted">
                        {page.body.split('\n').filter(Boolean).map((paragraph, i) =>
                            paragraph.startsWith('## ') ? (
                                <h2 key={i} className="pt-4 text-lg font-bold text-fg">
                                    {paragraph.replace('## ', '')}
                                </h2>
                            ) : (
                                <p key={i}>{paragraph}</p>
                            ),
                        )}
                    </div>
                </Card>
            </article>
        </MarketingLayout>
    );
}
