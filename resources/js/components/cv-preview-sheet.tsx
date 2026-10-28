import { router } from '@inertiajs/react';
import { Check, FileText, LoaderCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

export type PreviewCandidate = {
    id: number;
    name: string;
    category: string | null;
    source: string;
    review_status: string;
    has_pdf: boolean;
    pdf_url: string | null;
    full_text: string;
    excerpt: string;
    skills: string[];
    original_filename: string | null;
};

type Props = {
    candidateId: number | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function CvPreviewSheet({ candidateId, open, onOpenChange }: Props) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [candidate, setCandidate] = useState<PreviewCandidate | null>(null);
    const [tab, setTab] = useState<'pdf' | 'text'>('text');

    useEffect(() => {
        if (!open || !candidateId) {
            return;
        }

        let cancelled = false;
        setLoading(true);
        setError(null);
        setCandidate(null);

        fetch(`/candidates/${candidateId}/preview`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(async (res) => {
                if (!res.ok) {
                    throw new Error('Gagal memuat preview CV');
                }
                return res.json();
            })
            .then((data) => {
                if (cancelled) {
                    return;
                }
                const c = data.candidate as PreviewCandidate;
                setCandidate(c);
                setTab(c.has_pdf ? 'pdf' : 'text');
            })
            .catch((e: Error) => {
                if (!cancelled) {
                    setError(e.message || 'Gagal memuat preview');
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [open, candidateId]);

    function setStatus(review_status: string) {
        if (!candidate) {
            return;
        }
        router.patch(
            `/candidates/${candidate.id}/status`,
            { review_status },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setCandidate({ ...candidate, review_status });
                },
            },
        );
    }

    const shortlisted =
        candidate?.review_status === 'shortlist' ||
        candidate?.review_status === 'interview';

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="right"
                className="w-full gap-0 p-0 sm:max-w-xl md:max-w-2xl"
            >
                <SheetHeader className="border-b border-border/60 px-4 py-4 text-left">
                    <SheetTitle className="pr-8 text-base">
                        {candidate?.name || 'Preview CV'}
                    </SheetTitle>
                    <SheetDescription>
                        {candidate
                            ? `${formatCategory(candidate.category)} · ${candidate.source}`
                            : 'Memuat dokumen kandidat…'}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex min-h-0 flex-1 flex-col">
                    {loading ? (
                        <div className="flex flex-1 items-center justify-center gap-2 p-8 text-sm text-muted-foreground">
                            <LoaderCircle className="size-4 animate-spin" />
                            Memuat preview…
                        </div>
                    ) : null}

                    {error ? (
                        <div className="p-6 text-sm text-rose-600">{error}</div>
                    ) : null}

                    {candidate ? (
                        <>
                            <div className="flex items-center gap-2 border-b border-border/60 px-4 py-2">
                                <TabButton
                                    active={tab === 'pdf'}
                                    disabled={!candidate.has_pdf}
                                    onClick={() => setTab('pdf')}
                                >
                                    PDF
                                </TabButton>
                                <TabButton
                                    active={tab === 'text'}
                                    onClick={() => setTab('text')}
                                >
                                    Teks CV
                                </TabButton>
                                {candidate.original_filename ? (
                                    <span className="ml-auto truncate text-[11px] text-muted-foreground">
                                        {candidate.original_filename}
                                    </span>
                                ) : null}
                            </div>

                            <div className="min-h-0 flex-1 overflow-hidden bg-muted/20">
                                {tab === 'pdf' && candidate.has_pdf && candidate.pdf_url ? (
                                    <iframe
                                        title={`PDF ${candidate.name}`}
                                        src={candidate.pdf_url}
                                        className="h-full min-h-[70vh] w-full border-0 bg-white"
                                    />
                                ) : (
                                    <div className="h-full min-h-[70vh] overflow-y-auto p-4">
                                        <div className="mb-3 flex flex-wrap gap-1.5">
                                            {candidate.skills.map((s) => (
                                                <Badge key={s} variant="secondary">
                                                    {s}
                                                </Badge>
                                            ))}
                                        </div>
                                        <pre className="whitespace-pre-wrap font-sans text-[13px] leading-relaxed text-foreground/90">
                                            {candidate.full_text ||
                                                candidate.excerpt ||
                                                'Teks CV tidak tersedia.'}
                                        </pre>
                                    </div>
                                )}
                            </div>

                            <div className="flex flex-wrap gap-2 border-t border-border/60 bg-background p-4">
                                <Button
                                    className="bg-teal-800 hover:bg-teal-700 dark:bg-teal-600"
                                    disabled={shortlisted}
                                    onClick={() => setStatus('shortlist')}
                                >
                                    <Check className="size-3.5" />
                                    {shortlisted ? 'Sudah dipilih' : 'Pilih / Shortlist'}
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.visit(`/candidates/${candidate.id}`)
                                    }
                                >
                                    <FileText className="size-3.5" />
                                    Halaman penuh
                                </Button>
                            </div>
                        </>
                    ) : null}
                </div>
            </SheetContent>
        </Sheet>
    );
}

function TabButton({
    active,
    disabled,
    onClick,
    children,
}: {
    active: boolean;
    disabled?: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            disabled={disabled}
            onClick={onClick}
            className={`rounded-md px-3 py-1.5 text-xs font-medium transition disabled:cursor-not-allowed disabled:opacity-40 ${
                active
                    ? 'bg-teal-800 text-white dark:bg-teal-600'
                    : 'bg-muted text-muted-foreground hover:text-foreground'
            }`}
        >
            {children}
        </button>
    );
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
