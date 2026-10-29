import { Head, Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    Briefcase,
    MessageSquare,
    Sparkles,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Stats = {
    total_candidates: number;
    kaggle_count: number;
    upload_count: number;
    processing: number;
    open_jobs: number;
    chat_week: number;
    match_rate: number;
};

type Props = {
    stats: Stats;
    categoryDist: { category: string; total: number }[];
    jobs: {
        id: number;
        title: string;
        department: string | null;
        status: string;
        applicants: number;
    }[];
    recent: {
        id: number;
        name: string;
        category: string | null;
        source: string;
        review_status: string;
        created_at: string | null;
    }[];
};

export default function Dashboard({ stats, categoryDist, jobs, recent }: Props) {
    const maxCategory = Math.max(...categoryDist.map((c) => c.total), 1);

    return (
        <>
            <Head title="Dashboard" />
            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Dashboard"
                    description="Ringkasan korpus CV, lowongan aktif, dan aktivitas matching."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href="/candidates">Lihat kandidat</Link>
                            </Button>
                            <Button
                                asChild
                                className="bg-teal-800 hover:bg-teal-700 dark:bg-teal-600"
                            >
                                <Link href="/chat">
                                    Buka Chatbot
                                    <ArrowUpRight className="size-3.5" />
                                </Link>
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Metric
                        title="CV siap matching"
                        value={stats.total_candidates.toLocaleString('id-ID')}
                        hint={`${stats.kaggle_count.toLocaleString('id-ID')} Kaggle · ${stats.upload_count} unggahan`}
                        icon={Users}
                    />
                    <Metric
                        title="Antrean parse"
                        value={String(stats.processing)}
                        hint="File upload yang sedang diproses"
                        icon={Sparkles}
                    />
                    <Metric
                        title="Lowongan aktif"
                        value={String(stats.open_jobs)}
                        hint="Bisa dikunci di sesi chat"
                        icon={Briefcase}
                    />
                    <Metric
                        title="Query chat (7 hari)"
                        value={String(stats.chat_week)}
                        hint="Percakapan kriteria HR"
                        icon={MessageSquare}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-5">
                    <Card className="border-border/70 shadow-none lg:col-span-3">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">Distribusi kategori</CardTitle>
                            <CardDescription>
                                Top kategori dari dataset Kaggle + unggahan
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {categoryDist.length === 0 ? (
                                <EmptyHint>
                                    Belum ada data. Jalankan{' '}
                                    <code className="rounded bg-muted px-1.5 py-0.5 text-xs">
                                        php artisan jobmatch:import-kaggle
                                    </code>
                                </EmptyHint>
                            ) : (
                                categoryDist.map((row) => {
                                    const pct = Math.round((row.total / maxCategory) * 100);
                                    return (
                                        <div key={row.category} className="space-y-1.5">
                                            <div className="flex items-center justify-between gap-3 text-xs">
                                                <span className="truncate font-medium tracking-wide">
                                                    {formatCategory(row.category)}
                                                </span>
                                                <span className="tabular-nums text-muted-foreground">
                                                    {row.total}
                                                </span>
                                            </div>
                                            <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full rounded-full bg-teal-700/90 transition-all dark:bg-teal-400"
                                                    style={{ width: `${pct}%` }}
                                                />
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </CardContent>
                    </Card>

                    <Card className="border-border/70 shadow-none lg:col-span-2">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">Mulai screening</CardTitle>
                            <CardDescription>
                                Alur cepat: unggah → chat kriteria → shortlist
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            <Button
                                className="justify-between bg-teal-800 hover:bg-teal-700 dark:bg-teal-600"
                                asChild
                            >
                                <Link href="/chat">
                                    Chat kriteria ke Jobmatch
                                    <MessageSquare className="size-4 opacity-80" />
                                </Link>
                            </Button>
                            <Button variant="outline" className="justify-between" asChild>
                                <Link href="/uploads">
                                    Unggah CV / atur lowongan
                                    <Sparkles className="size-4 opacity-70" />
                                </Link>
                            </Button>
                            <div className="mt-2 rounded-lg border border-dashed bg-muted/30 p-3 text-xs leading-relaxed text-muted-foreground">
                                Tip: tulis seperti HR biasa — contoh “IT Python SQL 3
                                tahun” — bot akan ranking CV dari korpus.
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card className="border-border/70 shadow-none">
                        <CardHeader className="flex-row items-center justify-between space-y-0 pb-3">
                            <div>
                                <CardTitle className="text-base">Lowongan aktif</CardTitle>
                                <CardDescription>Parameter job untuk matching</CardDescription>
                            </div>
                            <Link
                                href="/uploads"
                                className="text-xs font-medium text-teal-800 hover:underline dark:text-teal-300"
                            >
                                Kelola
                            </Link>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {jobs.length === 0 ? (
                                <EmptyHint>Belum ada lowongan.</EmptyHint>
                            ) : (
                                jobs.map((job) => (
                                    <div
                                        key={job.id}
                                        className="flex items-center justify-between gap-3 rounded-lg border border-border/70 px-3 py-2.5"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {job.title}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {job.department || '—'} · {job.applicants}{' '}
                                                pelamar terhubung
                                            </p>
                                        </div>
                                        <Badge variant="secondary" className="shrink-0 capitalize">
                                            {job.status}
                                        </Badge>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card className="border-border/70 shadow-none">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">Aktivitas terbaru</CardTitle>
                            <CardDescription>CV yang baru masuk ke indeks</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {recent.length === 0 ? (
                                <EmptyHint>Belum ada kandidat.</EmptyHint>
                            ) : (
                                recent.map((c) => (
                                    <Link
                                        key={c.id}
                                        href={`/candidates/${c.id}`}
                                        className="flex items-center justify-between gap-3 rounded-lg border border-border/70 px-3 py-2.5 transition-colors hover:bg-muted/40"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {c.name}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {formatCategory(c.category)} · {c.source}
                                            </p>
                                        </div>
                                        <Badge variant="outline" className="shrink-0">
                                            {c.review_status}
                                        </Badge>
                                    </Link>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

function Metric({
    title,
    value,
    hint,
    icon: Icon,
}: {
    title: string;
    value: string;
    hint: string;
    icon: typeof Users;
}) {
    return (
        <Card className="border-border/70 shadow-none">
            <CardContent className="flex items-start justify-between gap-3 p-4">
                <div className="min-w-0 space-y-1">
                    <p className="text-xs font-medium text-muted-foreground">{title}</p>
                    <p className="text-2xl font-semibold tracking-tight tabular-nums">
                        {value}
                    </p>
                    <p className="text-[11px] text-muted-foreground">{hint}</p>
                </div>
                <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-teal-700/10 text-teal-800 dark:bg-teal-400/10 dark:text-teal-300">
                    <Icon className="size-4" />
                </div>
            </CardContent>
        </Card>
    );
}

function EmptyHint({ children }: { children: ReactNode }) {
    return <p className="text-sm text-muted-foreground">{children}</p>;
}

function formatCategory(category: string | null) {
    if (!category) {
        return 'Tanpa kategori';
    }
    return category
        .toLowerCase()
        .split('-')
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
};
