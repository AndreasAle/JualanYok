import { router } from '@inertiajs/react';
import { CheckCircle2, Circle, Lock, PlayCircle } from 'lucide-react';
import { useState } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { PageHeader } from '@/components/shared';
import { Alert, Badge, Button, Card, CardBody } from '@/components/ui';
import { toEmbedUrl } from '@/lib/embed';
import { cn, formatDate } from '@/lib/utils';

interface Lesson {
    id: number;
    title: string;
    type: string;
    duration_minutes: number;
    unlocked: boolean;
    unlocks_at: string | null;
    completed: boolean;
    body: string | null;
    video_url: string | null;
}

export default function CourseShow({
    enrollment,
    sections,
}: {
    enrollment: { id: number; title: string; progress: number; completed_at: string | null; certificate_code: string | null };
    sections: { title: string; description: string | null; lessons: Lesson[] }[];
}) {
    const allLessons = sections.flatMap((s) => s.lessons);
    const firstUnfinished = allLessons.find((l) => l.unlocked && !l.completed) ?? allLessons[0];

    const [activeId, setActiveId] = useState<number | null>(firstUnfinished?.id ?? null);
    const active = allLessons.find((l) => l.id === activeId) ?? null;

    const complete = (lesson: Lesson) => {
        router.post(
            `/member/kelas/${enrollment.id}/lesson/${lesson.id}/selesai`,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <DashboardLayout title={enrollment.title} area="member">
            <PageHeader
                title={enrollment.title}
                description={`${enrollment.progress}% selesai`}
                breadcrumbs={[{ label: 'Kelas Saya', href: '/member/kelas' }, { label: enrollment.title }]}
                actions={
                    enrollment.completed_at && (
                        <Badge tone="success">Selesai {formatDate(enrollment.completed_at)}</Badge>
                    )
                }
            />

            <div className="mb-5 h-2 rounded-full bg-surface-2">
                <div
                    className="h-full rounded-full gradient-brand transition-all"
                    style={{ width: `${Math.max(2, enrollment.progress)}%` }}
                />
            </div>

            {enrollment.certificate_code && enrollment.completed_at && (
                <div className="mb-5">
                    <Alert tone="success" title="Selamat, kamu menyelesaikan kelas ini!">
                        Nomor sertifikat kamu: <strong>{enrollment.certificate_code}</strong>
                    </Alert>
                </div>
            )}

            <div className="grid gap-4 lg:grid-cols-[1fr_340px]">
                {/* Player */}
                <Card>
                    <CardBody>
                        {!active ? (
                            <p className="py-10 text-center text-sm text-muted">Pilih materi dari daftar di samping.</p>
                        ) : !active.unlocked ? (
                            <div className="py-14 text-center">
                                <Lock className="mx-auto size-8 text-muted" />
                                <p className="mt-3 font-bold">Materi ini belum terbuka</p>
                                <p className="mt-1 text-sm text-muted">
                                    Bisa diakses mulai {formatDate(active.unlocks_at)}.
                                </p>
                            </div>
                        ) : (
                            <>
                                <h2 className="text-lg font-extrabold">{active.title}</h2>
                                <p className="mt-1 text-xs text-muted">{active.duration_minutes} menit</p>

                                {active.video_url && (
                                    <div className="mt-4 aspect-video overflow-hidden rounded-[var(--radius-card)] bg-black">
                                        <iframe
                                            src={toEmbedUrl(active.video_url)?.url ?? active.video_url}
                                            title={active.title}
                                            allowFullScreen
                                            className="size-full border-0"
                                        />
                                    </div>
                                )}

                                {active.body && (
                                    <div className="mt-4 space-y-3 text-sm leading-relaxed text-muted">
                                        {active.body.split('\n').filter(Boolean).map((paragraph, i) => (
                                            <p key={i}>{paragraph}</p>
                                        ))}
                                    </div>
                                )}

                                {!active.completed && (
                                    <Button variant="gradient" className="mt-5" onClick={() => complete(active)}>
                                        <CheckCircle2 className="size-4" />
                                        Tandai Selesai
                                    </Button>
                                )}
                            </>
                        )}
                    </CardBody>
                </Card>

                {/* Curriculum */}
                <div className="space-y-3 lg:max-h-[70vh] lg:overflow-y-auto">
                    {sections.map((section, i) => (
                        <Card key={i} className="p-4">
                            <p className="font-bold">{section.title}</p>
                            {section.description && (
                                <p className="mt-0.5 text-xs text-muted">{section.description}</p>
                            )}

                            <ul className="mt-3 space-y-1">
                                {section.lessons.map((lesson) => (
                                    <li key={lesson.id}>
                                        <button
                                            type="button"
                                            onClick={() => setActiveId(lesson.id)}
                                            disabled={!lesson.unlocked}
                                            className={cn(
                                                'flex w-full items-center gap-2.5 rounded-[var(--radius-field)] px-3 py-2.5 text-left text-sm transition-colors disabled:opacity-50',
                                                activeId === lesson.id
                                                    ? 'bg-brand-100 dark:bg-brand-900/40'
                                                    : 'hover:bg-surface-2',
                                            )}
                                        >
                                            {!lesson.unlocked ? (
                                                <Lock className="size-4 shrink-0 text-muted" />
                                            ) : lesson.completed ? (
                                                <CheckCircle2 className="size-4 shrink-0 text-[var(--success)]" />
                                            ) : (
                                                <Circle className="size-4 shrink-0 text-muted" />
                                            )}

                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate font-medium">{lesson.title}</span>
                                                <span className="block text-xs text-muted">
                                                    {lesson.duration_minutes} menit
                                                    {!lesson.unlocked &&
                                                        lesson.unlocks_at &&
                                                        ` · buka ${formatDate(lesson.unlocks_at)}`}
                                                </span>
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    ))}
                </div>
            </div>
        </DashboardLayout>
    );
}
