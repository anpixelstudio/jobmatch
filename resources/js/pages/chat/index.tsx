import { Form, Head, router } from '@inertiajs/react';
import { RotateCcw, Send, Sparkles, Users } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { AnalysisPayload } from '@/components/chat-analysis';
import { ChatMessageBubble } from '@/components/chat-message';
import { ChatTypingBubble } from '@/components/chat-typing';
import { CvPreviewSheet } from '@/components/cv-preview-sheet';
import { FieldSelect } from '@/components/field-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Message = {
    id: number;
    role: string;
    content: string;
    structured?: {
        opening: string;
        highlights?: string[];
        closing: string;
    } | null;
    picks?: Match[];
    analysis?: AnalysisPayload;
};

type Match = {
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
    };
};

type Props = {
    threadId: number | null;
    messages: Message[];
    matches: Match[];
    jobs: { id: number; title: string }[];
    stats: { ready_candidates: number };
    ai?: {
        enabled: boolean;
        provider: string;
        model: string;
    };
};

const prompts: { label: string; value: string }[] = [
    {
        label: 'IT · Python + SQL',
        value: 'Saya butuh kandidat IT yang kuat di Python dan SQL, minimal 3 tahun',
    },
    {
        label: 'HR · Excel · 2 thn',
        value: 'Cari kandidat HR pengalaman 2 tahun, bisa Excel',
    },
    {
        label: 'Bandingkan 1 vs 2',
        value: 'Bandingkan kandidat peringkat 1 dan 2',
    },
    {
        label: 'Skill gap #1',
        value: 'Analisa skill gap kandidat 1 terhadap syarat terakhir',
    },
];

export default function ChatIndex({
    threadId,
    messages,
    matches,
    jobs,
    stats,
    ai,
}: Props) {
    const [message, setMessage] = useState('');
    const [jobId, setJobId] = useState('');
    const [previewId, setPreviewId] = useState<number | null>(null);
    const [previewOpen, setPreviewOpen] = useState(false);
    const [isTyping, setIsTyping] = useState(false);
    const [pendingUserMessage, setPendingUserMessage] = useState<string | null>(
        null,
    );
    const bottomRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLTextAreaElement>(null);

    useEffect(() => {
        setIsTyping(false);
        setPendingUserMessage(null);
    }, [messages]);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, isTyping, pendingUserMessage]);

    function openPreview(id: number) {
        setPreviewId(id);
        setPreviewOpen(true);
    }

    function startTyping(text: string) {
        const trimmed = text.trim();
        if (!trimmed) {
            return;
        }
        setPendingUserMessage(trimmed);
        setIsTyping(true);
    }

    function applyPrompt(value: string) {
        setMessage(value);
        requestAnimationFrame(() => inputRef.current?.focus());
    }

    return (
        <>
            <Head title="Chatbot" />
            <div className="relative mx-auto flex h-[calc(100dvh-3.5rem)] w-full max-w-7xl flex-col gap-3 p-3 md:gap-4 md:p-5">
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top,_rgba(15,118,110,0.08),_transparent_55%),radial-gradient(ellipse_at_bottom_right,_rgba(15,118,110,0.05),_transparent_40%)]"
                />

                <header className="flex shrink-0 items-center justify-between gap-3">
                    <div className="min-w-0">
                        <div className="flex items-center gap-2">
                            <h1 className="truncate text-lg font-semibold tracking-tight md:text-xl">
                                Jobmatch Chat
                            </h1>
                            <span className="hidden rounded-full bg-teal-800/10 px-2 py-0.5 text-[10px] font-medium text-teal-900 sm:inline dark:text-teal-200">
                                {stats.ready_candidates.toLocaleString('id-ID')}{' '}
                                CV
                            </span>
                        </div>
                        <p className="truncate text-xs text-muted-foreground">
                            Matching · ringkasan · komparasi · skill gap
                        </p>
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                        {ai?.enabled ? (
                            <Badge
                                variant="secondary"
                                className="hidden font-normal sm:inline-flex"
                            >
                                {ai.model}
                            </Badge>
                        ) : null}
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8 text-muted-foreground"
                            onClick={() => router.post('/chat/reset')}
                        >
                            <RotateCcw className="size-3.5" />
                            <span className="hidden sm:inline">Reset</span>
                        </Button>
                    </div>
                </header>

                <div className="grid min-h-0 flex-1 gap-3 lg:grid-cols-[minmax(0,1fr)_300px]">
                    <section className="flex min-h-0 flex-col overflow-hidden rounded-2xl border border-teal-900/10 bg-card/90 shadow-[0_1px_0_rgba(15,118,110,0.06)] backdrop-blur-sm dark:border-teal-400/10">
                        <div className="flex items-center gap-3 border-b border-teal-900/8 px-4 py-3 dark:border-teal-400/10">
                            <div className="relative flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-teal-700 to-teal-900 text-white shadow-sm">
                                <Sparkles className="size-4" />
                                <span
                                    className={`absolute -right-0.5 -bottom-0.5 size-2.5 rounded-full border-2 border-card ${
                                        isTyping
                                            ? 'animate-pulse bg-amber-400'
                                            : 'bg-emerald-400'
                                    }`}
                                />
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-medium">Jobmatch</p>
                                <p className="truncate text-[11px] text-muted-foreground">
                                    {isTyping
                                        ? 'Sedang menganalisis kriteria…'
                                        : 'Asisten matching rekrutmen'}
                                </p>
                            </div>
                        </div>

                        <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-3 py-4 md:px-5">
                            {messages.length === 0 ? (
                                <EmptyState
                                    count={stats.ready_candidates}
                                    onPick={applyPrompt}
                                    active={message}
                                />
                            ) : (
                                messages.map((m) => (
                                    <ChatMessageBubble
                                        key={m.id}
                                        role={m.role}
                                        content={m.content}
                                        structured={m.structured}
                                        picks={m.picks ?? []}
                                        analysis={m.analysis}
                                        onPreviewCandidate={openPreview}
                                    />
                                ))
                            )}
                            {pendingUserMessage ? (
                                <ChatMessageBubble
                                    role="user"
                                    content={pendingUserMessage}
                                />
                            ) : null}
                            {isTyping ? (
                                <ChatTypingBubble label="Jobmatch sedang mengetik…" />
                            ) : null}
                            <div ref={bottomRef} />
                        </div>

                        <div className="shrink-0 border-t border-teal-900/8 bg-gradient-to-t from-muted/40 to-transparent p-3 md:p-4 dark:border-teal-400/10">
                            <div className="mb-2.5 flex [scrollbar-width:none] gap-1.5 overflow-x-auto pb-0.5 [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                                {prompts.map((p) => {
                                    const active = message === p.value;
                                    return (
                                        <button
                                            key={p.label}
                                            type="button"
                                            onClick={() => applyPrompt(p.value)}
                                            className={`shrink-0 rounded-full px-3 py-1 text-[11px] font-medium transition ${
                                                active
                                                    ? 'bg-teal-800 text-white shadow-sm'
                                                    : 'bg-background/80 text-muted-foreground ring-1 ring-teal-900/10 hover:text-foreground hover:ring-teal-800/30 dark:ring-teal-400/15'
                                            }`}
                                        >
                                            {p.label}
                                        </button>
                                    );
                                })}
                            </div>

                            <Form
                                action="/chat"
                                method="post"
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                onStart={() => {
                                    startTyping(message);
                                    setMessage('');
                                }}
                                onError={() => {
                                    setIsTyping(false);
                                    setPendingUserMessage(null);
                                }}
                                onCancel={() => {
                                    setIsTyping(false);
                                    setPendingUserMessage(null);
                                }}
                                onFinish={() => {
                                    window.setTimeout(() => {
                                        setIsTyping(false);
                                        setPendingUserMessage(null);
                                    }, 1200);
                                }}
                            >
                                {({ processing }) => (
                                    <div className="rounded-2xl bg-background p-2 shadow-sm ring-1 ring-teal-900/10 focus-within:ring-teal-700/40 dark:ring-teal-400/15">
                                        {threadId ? (
                                            <input
                                                type="hidden"
                                                name="thread_id"
                                                value={threadId}
                                            />
                                        ) : null}
                                        <textarea
                                            ref={inputRef}
                                            name="message"
                                            value={message}
                                            rows={2}
                                            onChange={(e) =>
                                                setMessage(e.target.value)
                                            }
                                            onKeyDown={(e) => {
                                                if (
                                                    e.key === 'Enter' &&
                                                    !e.shiftKey
                                                ) {
                                                    e.preventDefault();
                                                    if (
                                                        message.trim() &&
                                                        !processing &&
                                                        !isTyping
                                                    ) {
                                                        e.currentTarget.form?.requestSubmit();
                                                    }
                                                }
                                            }}
                                            placeholder="Contoh: kandidat IT Python, kasih alasan kenapa cocok…"
                                            className="max-h-28 min-h-[2.75rem] w-full resize-none bg-transparent px-2.5 py-2 text-sm outline-none placeholder:text-muted-foreground/70 disabled:opacity-60"
                                            required
                                            disabled={processing || isTyping}
                                        />
                                        <div className="flex items-center gap-2 px-1 pb-0.5">
                                            <FieldSelect
                                                name="job_opening_id"
                                                value={jobId}
                                                onValueChange={setJobId}
                                                disabled={
                                                    processing || isTyping
                                                }
                                                size="sm"
                                                side="top"
                                                className="max-w-[46%]"
                                                triggerClassName="h-8 border-0 bg-muted/60 shadow-none"
                                                options={[
                                                    {
                                                        value: '',
                                                        label: 'Tanpa lowongan',
                                                    },
                                                    ...jobs.map((j) => ({
                                                        value: String(j.id),
                                                        label: j.title,
                                                    })),
                                                ]}
                                                placeholder="Lowongan"
                                            />
                                            <div className="ml-auto flex items-center gap-2">
                                                {message ? (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setMessage('')
                                                        }
                                                        className="text-[11px] text-muted-foreground hover:text-foreground"
                                                    >
                                                        Hapus
                                                    </button>
                                                ) : null}
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    disabled={
                                                        processing ||
                                                        isTyping ||
                                                        !message.trim()
                                                    }
                                                    className="h-8 rounded-xl bg-teal-800 px-3 hover:bg-teal-700 dark:bg-teal-600 dark:hover:bg-teal-500"
                                                >
                                                    <Send className="size-3.5" />
                                                    Kirim
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </Form>
                        </div>
                    </section>

                    <aside className="flex min-h-0 flex-col overflow-hidden rounded-2xl border border-teal-900/10 bg-card/90 shadow-[0_1px_0_rgba(15,118,110,0.06)] backdrop-blur-sm dark:border-teal-400/10">
                        <div className="flex items-center gap-2 border-b border-teal-900/8 px-4 py-3 dark:border-teal-400/10">
                            <Users className="size-4 text-teal-800 dark:text-teal-400" />
                            <div className="min-w-0">
                                <p className="text-sm font-medium">
                                    Hasil match
                                </p>
                                <p className="text-[11px] text-muted-foreground">
                                    {matches.length > 0
                                        ? `${matches.length} kandidat terurut`
                                        : 'Menunggu kriteria'}
                                </p>
                            </div>
                        </div>
                        <div className="min-h-0 flex-1 space-y-2 overflow-y-auto p-3">
                            {matches.length === 0 ? (
                                <div className="flex h-full min-h-40 flex-col items-center justify-center gap-2 px-4 text-center">
                                    <div className="flex size-10 items-center justify-center rounded-full bg-teal-800/10 text-teal-800 dark:text-teal-300">
                                        <Users className="size-4" />
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        Belum ada ranking. Kirim kriteria di
                                        chat.
                                    </p>
                                </div>
                            ) : (
                                matches.map((m) => (
                                    <button
                                        key={m.id}
                                        type="button"
                                        onClick={() =>
                                            openPreview(m.candidate.id)
                                        }
                                        className="group block w-full rounded-xl bg-muted/25 p-3 text-left transition hover:bg-teal-800/8 hover:ring-1 hover:ring-teal-800/20"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">
                                                    <span className="mr-1.5 inline-flex size-5 items-center justify-center rounded-md bg-teal-800/10 text-[10px] font-semibold text-teal-900 tabular-nums dark:text-teal-200">
                                                        {m.rank}
                                                    </span>
                                                    {m.candidate.name}
                                                </p>
                                                <p className="mt-1 truncate text-[11px] text-muted-foreground">
                                                    {formatCategory(
                                                        m.candidate.category,
                                                    )}{' '}
                                                    · {m.candidate.source}
                                                </p>
                                            </div>
                                            <span className="shrink-0 rounded-lg bg-teal-800 px-2 py-0.5 text-[11px] font-semibold text-white tabular-nums">
                                                {m.score}%
                                            </span>
                                        </div>
                                        {m.reasons?.length ? (
                                            <p className="mt-2 line-clamp-2 text-[11px] leading-relaxed text-muted-foreground">
                                                {m.reasons[0]}
                                            </p>
                                        ) : null}
                                    </button>
                                ))
                            )}
                        </div>
                    </aside>
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

function EmptyState({
    count,
    onPick,
    active,
}: {
    count: number;
    onPick: (value: string) => void;
    active: string;
}) {
    return (
        <div className="mx-auto flex max-w-lg flex-col items-center gap-5 py-8 text-center md:py-12">
            <div className="flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-700 to-teal-900 text-white shadow-lg shadow-teal-900/20">
                <Sparkles className="size-6" />
            </div>
            <div className="space-y-2">
                <p className="text-base font-semibold tracking-tight">
                    Mau cari kandidat seperti apa?
                </p>
                <p className="text-sm text-pretty text-muted-foreground">
                    Jobmatch mencocokkan dari {count.toLocaleString('id-ID')} CV
                    siap pakai. Pilih contoh atau ketik kriteria sendiri.
                </p>
            </div>
            <div className="grid w-full gap-2 sm:grid-cols-2">
                {prompts.map((p) => (
                    <button
                        key={p.label}
                        type="button"
                        onClick={() => onPick(p.value)}
                        className={`rounded-xl px-3.5 py-3 text-left text-xs leading-relaxed transition ${
                            active === p.value
                                ? 'bg-teal-800 text-white shadow-sm'
                                : 'bg-muted/40 text-muted-foreground ring-1 ring-teal-900/8 hover:bg-muted/70 hover:text-foreground dark:ring-teal-400/10'
                        }`}
                    >
                        <span className="mb-1 block text-[10px] font-semibold tracking-wide uppercase opacity-70">
                            Coba
                        </span>
                        {p.value}
                    </button>
                ))}
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

ChatIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Chatbot', href: '/chat' },
    ],
};
