import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { CvPreviewSheet } from '@/components/cv-preview-sheet';
import { FieldSelect } from '@/components/field-select';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type CandidateRow = {
    id: number;
    name: string;
    category: string | null;
    source: string;
    review_status: string;
    excerpt: string;
};

type Props = {
    candidates: {
        data: CandidateRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    categories: string[];
    filters: { q: string; category: string; source: string };
};

export default function CandidatesIndex({
    candidates,
    categories,
    filters,
}: Props) {
    const [q, setQ] = useState(filters.q || '');
    const [category, setCategory] = useState(filters.category || '');
    const [source, setSource] = useState(filters.source || '');
    const [previewId, setPreviewId] = useState<number | null>(null);
    const [previewOpen, setPreviewOpen] = useState(false);

    useEffect(() => {
        setQ(filters.q || '');
        setCategory(filters.category || '');
        setSource(filters.source || '');
    }, [filters.q, filters.category, filters.source]);

    function applyFilters(e?: React.FormEvent) {
        e?.preventDefault();
        router.get(
            '/candidates',
            { q, category, source },
            { preserveScroll: true, replace: true },
        );
    }

    function openPreview(id: number) {
        setPreviewId(id);
        setPreviewOpen(true);
    }

    const categoryOptions = useMemo(
        () => [
            { value: '', label: 'Semua kategori' },
            ...categories.map((c) => ({
                value: c,
                label: c
                    .toLowerCase()
                    .split('-')
                    .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
                    .join(' '),
            })),
        ],
        [categories],
    );

    const sourceOptions = [
        { value: '', label: 'Semua sumber' },
        { value: 'kaggle', label: 'Kaggle' },
        { value: 'upload', label: 'Upload' },
    ];

    return (
        <>
            <Head title="Kandidat" />
            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Kandidat"
                    description="Bank CV dari Kaggle dan unggahan HR."
                    actions={
                        <Button
                            asChild
                            className="bg-teal-800 hover:bg-teal-700 dark:bg-teal-600"
                        >
                            <Link href="/chat">Cari via Chatbot</Link>
                        </Button>
                    }
                />

                <form
                    onSubmit={applyFilters}
                    className="grid gap-2 rounded-2xl border border-teal-900/10 bg-card/90 p-3 shadow-sm md:grid-cols-[1fr_200px_150px_auto] dark:border-teal-400/10"
                >
                    <div className="relative">
                        <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                        <Input
                            className="border-border/70 pl-9 shadow-none"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Cari skill, posisi, kata kunci..."
                        />
                    </div>
                    <FieldSelect
                        value={category}
                        onValueChange={setCategory}
                        options={categoryOptions}
                        placeholder="Kategori"
                    />
                    <FieldSelect
                        value={source}
                        onValueChange={setSource}
                        options={sourceOptions}
                        placeholder="Sumber"
                    />
                    <Button
                        type="submit"
                        className="bg-teal-800 hover:bg-teal-700 dark:bg-teal-600"
                    >
                        Terapkan
                    </Button>
                </form>

                <div className="overflow-hidden rounded-2xl border border-teal-900/10 bg-card shadow-sm dark:border-teal-400/10">
                    {candidates.data.length === 0 ? (
                        <p className="px-4 py-12 text-center text-sm text-muted-foreground">
                            Tidak ada kandidat. Import dataset atau unggah CV.
                        </p>
                    ) : (
                        <div className="divide-y divide-border/50">
                            {candidates.data.map((c) => (
                                <button
                                    key={c.id}
                                    type="button"
                                    onClick={() => openPreview(c.id)}
                                    className="flex w-full flex-col gap-2 px-4 py-3.5 text-left transition-colors hover:bg-teal-800/[0.04] sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium">
                                            {c.name}
                                        </p>
                                        <p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground">
                                            {c.excerpt}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 flex-wrap gap-1.5">
                                        <Badge variant="outline">{c.source}</Badge>
                                        <Badge variant="secondary">
                                            {c.category || '—'}
                                        </Badge>
                                        <Badge className="bg-teal-800/10 text-teal-900 hover:bg-teal-800/10 dark:text-teal-200">
                                            {c.review_status}
                                        </Badge>
                                    </div>
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                <div className="flex flex-wrap gap-1.5">
                    {candidates.links.map((link, i) => (
                        <Button
                            key={`${link.label}-${i}`}
                            variant={link.active ? 'default' : 'outline'}
                            size="sm"
                            disabled={!link.url}
                            onClick={() => link.url && router.get(link.url)}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>

                <CvPreviewSheet
                    candidateId={previewId}
                    open={previewOpen}
                    onOpenChange={setPreviewOpen}
                />
            </div>
        </>
    );
}

CandidatesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Kandidat', href: '/candidates' },
    ],
};
