import { router } from '@inertiajs/react';
import { Check, Eye } from 'lucide-react';
import {
    type AnalysisPayload,
    ChatAnalysisPanel,
    type SkillGapAnalysis,
    type SummaryAnalysis,
} from '@/components/chat-analysis';
import { Button } from '@/components/ui/button';

type Structured = {
    opening: string;
    highlights?: string[];
    closing: string;
};

type PickCandidate = {
    id: number;
    score: number;
    rank: number;
    reasons: string[];
    candidate: {
        id: number;
        name: string;
        category: string | null;
        source: string;
        review_status: string;
        external_id?: string | null;
    };
    summary?: SummaryAnalysis | null;
    skill_gap?: SkillGapAnalysis | null;
};

type Props = {
    role: string;
    content: string;
    structured?: Structured | null;
    picks?: PickCandidate[];
    analysis?: AnalysisPayload;
    onPreviewCandidate?: (candidateId: number) => void;
};

export function ChatMessageBubble({
    role,
    content,
    structured,
    picks = [],
    analysis,
    onPreviewCandidate,
}: Props) {
    const isUser = role === 'user';

    return (
        <div className={`flex ${isUser ? 'justify-end' : 'justify-start'}`}>
            <div
                className={`w-full max-w-[min(100%,40rem)] text-sm leading-relaxed ${
                    isUser
                        ? 'rounded-2xl rounded-br-md bg-teal-800 px-3.5 py-2.5 text-white shadow-sm dark:bg-teal-600'
                        : 'space-y-3'
                }`}
            >
                {isUser ? (
                    <p className="whitespace-pre-wrap">
                        {stripMarkdown(content)}
                    </p>
                ) : (
                    <>
                        {(structured || picks.length > 0 || analysis) && (
                            <div className="rounded-2xl rounded-bl-md bg-muted/35 px-3.5 py-3 ring-1 ring-teal-900/6 dark:ring-teal-400/10">
                                <p className="text-pretty">
                                    {cleanAssistantText(
                                        structured?.opening || content,
                                        Boolean(structured?.opening),
                                    )}
                                </p>
                                {structured?.closing ? (
                                    <p className="mt-2 text-xs text-pretty text-muted-foreground">
                                        {cleanAssistantText(
                                            structured.closing,
                                            true,
                                        )}
                                    </p>
                                ) : null}
                            </div>
                        )}

                        {!structured && !picks.length && !analysis ? (
                            <div className="rounded-2xl rounded-bl-md bg-muted/35 px-3.5 py-3 ring-1 ring-teal-900/6 dark:ring-teal-400/10">
                                <p className="whitespace-pre-wrap">
                                    {cleanAssistantText(content, false)}
                                </p>
                            </div>
                        ) : null}

                        <ChatAnalysisPanel analysis={analysis} />

                        {picks.length > 0 ? (
                            <div className="space-y-2">
                                <p className="px-0.5 text-[10px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                                    Rekomendasi · {Math.min(picks.length, 5)}{' '}
                                    teratas
                                </p>
                                {picks.slice(0, 5).map((pick) => (
                                    <CandidatePickCard
                                        key={pick.id}
                                        pick={pick}
                                        onPreview={() =>
                                            onPreviewCandidate?.(
                                                pick.candidate.id,
                                            )
                                        }
                                    />
                                ))}
                                {picks.length > 5 ? (
                                    <p className="px-0.5 text-[11px] text-muted-foreground">
                                        +{picks.length - 5} di panel kanan
                                    </p>
                                ) : null}
                            </div>
                        ) : null}
                    </>
                )}
            </div>
        </div>
    );
}

function CandidatePickCard({
    pick,
    onPreview,
}: {
    pick: PickCandidate;
    onPreview: () => void;
}) {
    const { candidate, score, rank, reasons, summary, skill_gap } = pick;
    const shortlisted =
        candidate.review_status === 'shortlist' ||
        candidate.review_status === 'interview';
    const idLabel = candidate.external_id || String(candidate.id);

    function setStatus(review_status: string) {
        router.patch(
            `/candidates/${candidate.id}/status`,
            { review_status },
            { preserveScroll: true },
        );
    }

    return (
        <div className="rounded-2xl bg-background p-3.5 shadow-sm ring-1 ring-teal-900/8 transition hover:ring-teal-800/25 dark:ring-teal-400/12">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex items-center gap-2">
                        <span className="flex size-6 shrink-0 items-center justify-center rounded-lg bg-teal-800 text-[11px] font-semibold text-white dark:bg-teal-600">
                            {rank}
                        </span>
                        <p className="truncate font-medium">{candidate.name}</p>
                    </div>
                    <p className="mt-1 pl-8 text-[11px] text-muted-foreground">
                        {formatCategory(candidate.category)} · ID {idLabel}
                        {summary?.headline ? ` · ${summary.headline}` : ''}
                    </p>
                </div>
                <span className="shrink-0 rounded-lg bg-teal-800/10 px-2 py-1 text-xs font-semibold text-teal-900 tabular-nums dark:text-teal-200">
                    {score}%
                </span>
            </div>

            {summary?.skills && summary.skills.length > 0 ? (
                <div className="mt-2 flex flex-wrap gap-1 pl-8">
                    {summary.skills.slice(0, 6).map((s) => (
                        <span
                            key={s}
                            className="rounded-md bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground"
                        >
                            {s}
                        </span>
                    ))}
                </div>
            ) : null}

            {skill_gap ? (
                <div className="mt-3 grid grid-cols-2 gap-2">
                    <div className="rounded-xl bg-emerald-500/8 px-2.5 py-2">
                        <p className="text-[10px] font-semibold text-emerald-800 dark:text-emerald-300">
                            Sudah ada ({skill_gap.matched.length})
                        </p>
                        <p className="mt-1 line-clamp-3 text-[10px] leading-snug text-foreground/80">
                            {skill_gap.matched.length
                                ? skill_gap.matched.join(' · ')
                                : '—'}
                        </p>
                    </div>
                    <div className="rounded-xl bg-rose-500/8 px-2.5 py-2">
                        <p className="text-[10px] font-semibold text-rose-800 dark:text-rose-300">
                            Belum ada ({skill_gap.missing.length})
                        </p>
                        <p className="mt-1 line-clamp-3 text-[10px] leading-snug text-foreground/80">
                            {skill_gap.missing.length
                                ? skill_gap.missing.join(' · ')
                                : 'Tidak ada gap'}
                        </p>
                    </div>
                </div>
            ) : reasons.length > 0 ? (
                <div className="mt-3 rounded-xl bg-teal-800/[0.04] px-3 py-2 dark:bg-teal-400/5">
                    <p className="mb-1 text-[10px] font-semibold tracking-wide text-teal-900/70 uppercase dark:text-teal-200/70">
                        Alasan cocok
                    </p>
                    <ul className="space-y-1">
                        {reasons.slice(0, 3).map((reason) => (
                            <li
                                key={reason}
                                className="text-[11px] leading-snug text-foreground/85"
                            >
                                {reason}
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}

            <div className="mt-3 flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    className="h-8 rounded-xl"
                    onClick={onPreview}
                >
                    <Eye className="size-3.5" />
                    Preview CV
                </Button>
                <Button
                    size="sm"
                    className="h-8 rounded-xl bg-teal-800 hover:bg-teal-700 dark:bg-teal-600"
                    disabled={shortlisted}
                    onClick={() => setStatus('shortlist')}
                >
                    <Check className="size-3.5" />
                    {shortlisted ? 'Sudah dipilih' : 'Shortlist'}
                </Button>
            </div>
        </div>
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

function stripMarkdown(text: string): string {
    return text
        .replace(/\*\*(.*?)\*\*/g, '$1')
        .replace(/__(.*?)__/g, '$1')
        .replace(/^\s*[-*]\s+/gm, '')
        .replace(/`([^`]+)`/g, '$1')
        .trim();
}

function cleanAssistantText(text: string, openingOnly: boolean): string {
    const cleaned = stripMarkdown(text);
    const lines = cleaned
        .split(/\n+/)
        .map((l) => l.trim())
        .filter(Boolean)
        .filter(
            (l) =>
                !/peringkat\s*\d/i.test(l) &&
                !/^\d+\.\s/.test(l) &&
                !/\(\d{1,3}%\)/.test(l) &&
                !/kandidat\s+[A-Z][A-Z\s-]*#\d+/i.test(l),
        );

    if (openingOnly) {
        return (
            lines.slice(0, 2).join(' ') || cleaned.split(/\n+/)[0] || cleaned
        );
    }

    return lines.join('\n') || cleaned;
}
