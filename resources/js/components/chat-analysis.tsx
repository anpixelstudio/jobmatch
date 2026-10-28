import { Check, Minus, X } from 'lucide-react';

export type SummaryAnalysis = {
    type?: 'summary';
    candidate_id: number;
    external_id?: string | null;
    name: string;
    category?: string | null;
    headline?: string;
    years?: number | null;
    skills?: string[];
    highlights?: string[];
    summary?: string;
};

export type SkillGapAnalysis = {
    type?: 'skill_gap';
    candidate_id: number;
    external_id?: string | null;
    name: string;
    requirements: string[];
    matched: string[];
    missing: string[];
    coverage: number;
    total: number;
    notes?: string[];
};

export type CompareAnalysis = {
    type: 'compare';
    left: SummaryAnalysis & { score?: number | null; gap?: SkillGapAnalysis | null };
    right: SummaryAnalysis & { score?: number | null; gap?: SkillGapAnalysis | null };
    rows: {
        label: string;
        left: string;
        right: string;
        winner: 'left' | 'right' | 'tie' | 'na';
    }[];
    verdict: string;
    winner_id?: number | null;
};

export type AnalysisPayload =
    | CompareAnalysis
    | SkillGapAnalysis
    | SummaryAnalysis
    | null
    | undefined;

export function ChatAnalysisPanel({ analysis }: { analysis: AnalysisPayload }) {
    if (!analysis || typeof analysis !== 'object') {
        return null;
    }

    if ('type' in analysis && analysis.type === 'compare') {
        return <ComparePanel analysis={analysis} />;
    }

    if (
        ('type' in analysis && analysis.type === 'skill_gap') ||
        ('matched' in analysis && 'missing' in analysis)
    ) {
        return <SkillGapPanel gap={analysis as SkillGapAnalysis} />;
    }

    if ('summary' in analysis || ('skills' in analysis && 'name' in analysis)) {
        return <SummaryPanel summary={analysis as SummaryAnalysis} />;
    }

    return null;
}

export function SummaryPanel({ summary }: { summary: SummaryAnalysis }) {
    return (
        <div className="rounded-2xl bg-background p-3.5 shadow-sm ring-1 ring-teal-900/8 dark:ring-teal-400/12">
            <p className="text-[10px] font-semibold tracking-[0.14em] text-teal-900/70 uppercase dark:text-teal-200/70">
                Ringkasan kualifikasi
            </p>
            <p className="mt-1 text-sm font-medium">{summary.name}</p>
            {summary.headline ? (
                <p className="text-[11px] text-muted-foreground">{summary.headline}</p>
            ) : null}
            <div className="mt-2 flex flex-wrap gap-1.5">
                {summary.years != null ? (
                    <span className="rounded-md bg-muted px-2 py-0.5 text-[10px]">
                        ~{summary.years} thn
                    </span>
                ) : null}
                {(summary.skills ?? []).slice(0, 8).map((s) => (
                    <span
                        key={s}
                        className="rounded-md bg-teal-800/10 px-2 py-0.5 text-[10px] text-teal-900 dark:text-teal-200"
                    >
                        {s}
                    </span>
                ))}
            </div>
            {summary.summary ? (
                <p className="mt-2 text-[11px] leading-relaxed text-muted-foreground text-pretty">
                    {summary.summary}
                </p>
            ) : null}
        </div>
    );
}

export function SkillGapPanel({ gap }: { gap: SkillGapAnalysis }) {
    return (
        <div className="rounded-2xl bg-background p-3.5 shadow-sm ring-1 ring-teal-900/8 dark:ring-teal-400/12">
            <div className="flex items-start justify-between gap-2">
                <div>
                    <p className="text-[10px] font-semibold tracking-[0.14em] text-teal-900/70 uppercase dark:text-teal-200/70">
                        Skill gap
                    </p>
                    <p className="mt-1 text-sm font-medium">{gap.name}</p>
                </div>
                <span className="rounded-lg bg-teal-800 px-2 py-1 text-[11px] font-semibold text-white tabular-nums">
                    {gap.coverage}%
                </span>
            </div>

            <div className="mt-3 grid gap-2 sm:grid-cols-2">
                <div className="rounded-xl bg-emerald-500/8 p-2.5">
                    <p className="mb-1.5 flex items-center gap-1 text-[10px] font-semibold text-emerald-800 uppercase dark:text-emerald-300">
                        <Check className="size-3" /> Sudah ada
                    </p>
                    {gap.matched.length === 0 ? (
                        <p className="text-[11px] text-muted-foreground">Belum ada yang terpenuhi</p>
                    ) : (
                        <ul className="space-y-1">
                            {gap.matched.map((item) => (
                                <li key={item} className="text-[11px] leading-snug">
                                    {item}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
                <div className="rounded-xl bg-rose-500/8 p-2.5">
                    <p className="mb-1.5 flex items-center gap-1 text-[10px] font-semibold text-rose-800 uppercase dark:text-rose-300">
                        <X className="size-3" /> Belum ada
                    </p>
                    {gap.missing.length === 0 ? (
                        <p className="text-[11px] text-muted-foreground">Tidak ada gap signifikan</p>
                    ) : (
                        <ul className="space-y-1">
                            {gap.missing.map((item) => (
                                <li key={item} className="text-[11px] leading-snug">
                                    {item}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>

            {gap.notes && gap.notes.length > 0 ? (
                <ul className="mt-2 space-y-0.5">
                    {gap.notes.map((n) => (
                        <li key={n} className="text-[11px] text-muted-foreground">
                            {n}
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}

function ComparePanel({ analysis }: { analysis: CompareAnalysis }) {
    return (
        <div className="overflow-hidden rounded-2xl bg-background shadow-sm ring-1 ring-teal-900/8 dark:ring-teal-400/12">
            <div className="border-b border-teal-900/8 px-3.5 py-3 dark:border-teal-400/10">
                <p className="text-[10px] font-semibold tracking-[0.14em] text-teal-900/70 uppercase dark:text-teal-200/70">
                    Komparasi kandidat
                </p>
                <p className="mt-1 text-sm text-pretty">{analysis.verdict}</p>
            </div>

            <div className="grid grid-cols-2 gap-px bg-teal-900/8 dark:bg-teal-400/10">
                <div className="bg-background p-3">
                    <p className="truncate text-xs font-semibold">{analysis.left.name}</p>
                    <p className="text-[10px] text-muted-foreground">
                        {analysis.left.score != null ? `${analysis.left.score}% match` : '—'}
                    </p>
                </div>
                <div className="bg-background p-3">
                    <p className="truncate text-xs font-semibold">{analysis.right.name}</p>
                    <p className="text-[10px] text-muted-foreground">
                        {analysis.right.score != null ? `${analysis.right.score}% match` : '—'}
                    </p>
                </div>
            </div>

            <div className="divide-y divide-teal-900/6 dark:divide-teal-400/10">
                {analysis.rows.map((row) => (
                    <div key={row.label} className="grid grid-cols-[5.5rem_1fr_1fr] gap-2 px-3 py-2.5 text-[11px]">
                        <span className="font-medium text-muted-foreground">{row.label}</span>
                        <span
                            className={
                                row.winner === 'left'
                                    ? 'font-semibold text-teal-900 dark:text-teal-200'
                                    : ''
                            }
                        >
                            {row.left}
                        </span>
                        <span
                            className={
                                row.winner === 'right'
                                    ? 'font-semibold text-teal-900 dark:text-teal-200'
                                    : ''
                            }
                        >
                            {row.right}
                        </span>
                    </div>
                ))}
            </div>

            {(analysis.left.gap || analysis.right.gap) && (
                <div className="grid gap-2 border-t border-teal-900/8 p-3 sm:grid-cols-2 dark:border-teal-400/10">
                    {analysis.left.gap ? (
                        <MiniGap name={analysis.left.name} gap={analysis.left.gap} />
                    ) : null}
                    {analysis.right.gap ? (
                        <MiniGap name={analysis.right.name} gap={analysis.right.gap} />
                    ) : null}
                </div>
            )}
        </div>
    );
}

function MiniGap({ name, gap }: { name: string; gap: SkillGapAnalysis }) {
    return (
        <div className="rounded-xl bg-muted/40 p-2.5">
            <p className="truncate text-[10px] font-semibold">{name}</p>
            <p className="mt-1 flex items-center gap-2 text-[10px] text-muted-foreground">
                <span className="inline-flex items-center gap-0.5 text-emerald-700 dark:text-emerald-300">
                    <Check className="size-3" />
                    {gap.matched.length}
                </span>
                <span className="inline-flex items-center gap-0.5 text-rose-700 dark:text-rose-300">
                    <Minus className="size-3" />
                    {gap.missing.length} gap
                </span>
                <span className="ml-auto tabular-nums">{gap.coverage}%</span>
            </p>
        </div>
    );
}
