import { Link } from '@inertiajs/react';
import { Award, BookOpen, Lock } from 'lucide-react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { PageHeader } from '@/components/shared';
import { Badge, Card, EmptyState } from '@/components/ui';
import { formatDate } from '@/lib/utils';

interface Enrollment {
    id: number;
    title: string;
    thumbnail_url: string | null;
    progress: number;
    lesson_count: number;
    expires_at: string | null;
    has_access: boolean;
    completed_at: string | null;
    certificate_code: string | null;
}

export default function MemberCourses({ enrollments }: { enrollments: Enrollment[] }) {
    return (
        <DashboardLayout title="Kelas Saya" area="member">
            <PageHeader title="Kelas Saya" description="Lanjutin belajar dari mana kamu berhenti." />

            {enrollments.length === 0 ? (
                <Card>
                    <EmptyState
                        icon={<BookOpen className="size-6" />}
                        title="Belum ikut kelas"
                        description="Kelas yang kamu beli otomatis muncul di sini."
                    />
                </Card>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {enrollments.map((enrollment) => {
                        const inner = (
                            <Card className="flex h-full flex-col overflow-hidden transition-shadow hover:shadow-lift">
                                {enrollment.thumbnail_url ? (
                                    <img
                                        src={enrollment.thumbnail_url}
                                        alt=""
                                        className="aspect-video w-full object-cover"
                                    />
                                ) : (
                                    <div className="aspect-video w-full gradient-brand" />
                                )}

                                <div className="flex flex-1 flex-col p-4">
                                    <p className="font-bold leading-snug">{enrollment.title}</p>
                                    <p className="mt-1 text-xs text-muted">{enrollment.lesson_count} materi</p>

                                    <div className="mt-3 flex-1">
                                        <div className="h-2 rounded-full bg-surface-2">
                                            <div
                                                className="h-full rounded-full gradient-brand transition-all"
                                                style={{ width: `${Math.max(3, enrollment.progress)}%` }}
                                            />
                                        </div>
                                        <p className="mt-1.5 text-xs text-muted">{enrollment.progress}% selesai</p>
                                    </div>

                                    <div className="mt-3 flex flex-wrap gap-1.5">
                                        {!enrollment.has_access && (
                                            <Badge tone="danger">
                                                <Lock className="size-3" />
                                                Akses berakhir
                                            </Badge>
                                        )}
                                        {enrollment.completed_at && (
                                            <Badge tone="success">
                                                <Award className="size-3" />
                                                Selesai
                                            </Badge>
                                        )}
                                        {enrollment.expires_at && enrollment.has_access && (
                                            <Badge>Sampai {formatDate(enrollment.expires_at)}</Badge>
                                        )}
                                    </div>

                                    {enrollment.certificate_code && enrollment.completed_at && (
                                        <p className="mt-2 font-mono text-[11px] text-muted">
                                            Sertifikat: {enrollment.certificate_code}
                                        </p>
                                    )}
                                </div>
                            </Card>
                        );

                        return enrollment.has_access ? (
                            <Link key={enrollment.id} href={`/member/kelas/${enrollment.id}`} className="block">
                                {inner}
                            </Link>
                        ) : (
                            <div key={enrollment.id} className="opacity-60">
                                {inner}
                            </div>
                        );
                    })}
                </div>
            )}
        </DashboardLayout>
    );
}
