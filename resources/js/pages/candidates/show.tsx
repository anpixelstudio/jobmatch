import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, MessageSquare } from 'lucide-react';
import { useState } from 'react';
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

type Candidate = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    location: string | null;
    category: string | null;
    source: string;
    status: string;
    review_status: string;
    original_filename: string | null;
    has_pdf: boolean;
    pdf_url: string | null;
    excerpt: string;
    full_text: string;
    skills: string[];
    timeline: { title: string; detail: string }[];
    created_at: string | null;
};

type Props = { candidate: Candidate };

export default function CandidateShow({ candidate }: Props) {
    const [tab, setTab] = useState<'pdf' | 'text'>(
        candidate.has_pdf ? 'pdf' : 'text',
    );

    function setStatus(review_status: string) {
        router.patch(`/candidates/${candidate.id}/status`, { review_status });
    }

    return (
        <>
            <Head title={candidate.name} />
            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={candidate.name}
                    description={`${candidate.category || 'Tanpa kategori'} · ${candidate.source} · ${candidate.created_at || ''}`}
                    actions={
                        <>
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/candidates">
                                    <ArrowLeft className="size-3.5" />
                                    Kembali
                                </Link>
                            </Button>
                            <Button size="sm" asChild>
                                <Link href="/chat">
                                    <MessageSquare className="size-3.5" />
                                    Tanya Jobmatch
                                </Link>
                            </Button>
                        </>
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    <Badge className="bg-teal-800 text-white hover:bg-teal-800 dark:bg-teal-600">
                        {candidate.review_status}
                    </Badge>
                    {candidate.email ? (
                        <span className="text-xs text-muted-foreground">
                            {candidate.email}
                        </span>
                    ) : null}
                    {candidate.phone ? (
                        <span className="text-xs text-muted-foreground">
                            {candidate.phone}
                        </span>
                    ) : null}
                    {candidate.location ? (
                        <span className="text-xs text-muted-foreground">
                            {candidate.location}
                        </span>
                    ) : null}
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        <Card className="overflow-hidden border-border/70 shadow-none">
                            <CardHeader className="flex-row items-center justify-between space-y-0 border-b border-border/60 pb-3">
                                <div>
                                    <CardTitle className="text-base">Preview CV</CardTitle>
                                    <CardDescription>
                                        {candidate.original_filename || 'Dokumen kandidat'}
                                    </CardDescription>
                                </div>
                                <div className="flex gap-1.5">
                                    <button
                                        type="button"
                                        disabled={!candidate.has_pdf}
                                        onClick={() => setTab('pdf')}
                                        className={`rounded-md px-3 py-1.5 text-xs font-medium disabled:opacity-40 ${
                                            tab === 'pdf'
                                                ? 'bg-teal-800 text-white'
                                                : 'bg-muted text-muted-foreground'
                                        }`}
                                    >
                                        PDF
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setTab('text')}
                                        className={`rounded-md px-3 py-1.5 text-xs font-medium ${
                                            tab === 'text'
                                                ? 'bg-teal-800 text-white'
                                                : 'bg-muted text-muted-foreground'
                                        }`}
                                    >
                                        Teks
                                    </button>
                                </div>
                            </CardHeader>
                            <CardContent className="p-0">
                                {tab === 'pdf' &&
                                candidate.has_pdf &&
                                candidate.pdf_url ? (
                                    <iframe
                                        title={`PDF ${candidate.name}`}
                                        src={candidate.pdf_url}
                                        className="h-[70vh] w-full border-0 bg-white"
                                    />
                                ) : (
                                    <div className="max-h-[70vh] overflow-y-auto p-4">
                                        <pre className="whitespace-pre-wrap font-sans text-sm leading-relaxed text-muted-foreground">
                                            {candidate.full_text ||
                                                candidate.excerpt ||
                                                'Teks CV tidak tersedia.'}
                                        </pre>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="border-border/70 shadow-none">
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">Skill terdeteksi</CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-wrap gap-1.5">
                                {candidate.skills.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Belum ada skill yang terdeteksi.
                                    </p>
                                ) : (
                                    candidate.skills.map((s) => (
                                        <Badge key={s} variant="secondary">
                                            {s}
                                        </Badge>
                                    ))
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <Card className="h-fit border-border/70 shadow-none">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">Keputusan HR</CardTitle>
                            <CardDescription>
                                Status tersimpan ke pipeline Jobmatch
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <Button
                                className="w-full bg-teal-800 hover:bg-teal-700 dark:bg-teal-600"
                                onClick={() => setStatus('interview')}
                            >
                                Loloskan ke wawancara
                            </Button>
                            <Button
                                variant="secondary"
                                className="w-full"
                                onClick={() => setStatus('shortlist')}
                            >
                                Shortlist
                            </Button>
                            <Button
                                variant="outline"
                                className="w-full"
                                onClick={() => setStatus('talent_pool')}
                            >
                                Simpan ke talent pool
                            </Button>
                            <Button
                                variant="destructive"
                                className="w-full"
                                onClick={() => setStatus('rejected')}
                            >
                                Tolak kandidat
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

CandidateShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Kandidat', href: '/candidates' },
        { title: 'Detail', href: '#' },
    ],
};
