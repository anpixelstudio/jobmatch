<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\JobOpening;
use App\Models\MatchResult;
use App\Services\CandidateAnalyzer;
use App\Services\GeminiClient;
use App\Services\ResumeMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function index(Request $request, GeminiClient $gemini): Response
    {
        $thread = ChatThread::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->first();

        $messages = [];
        $matches = [];

        if ($thread) {
            $messages = $thread->messages()
                ->with(['matchResults.candidate'])
                ->get()
                ->map(function (ChatMessage $m) {
                    $analyses = is_array($m->meta_json['analyses'] ?? null)
                        ? $m->meta_json['analyses']
                        : [];
                    $analysis = is_array($m->meta_json['analysis'] ?? null)
                        ? $m->meta_json['analysis']
                        : null;

                    $picks = $m->role === 'assistant'
                        ? $m->matchResults->map(function (MatchResult $r) use ($analyses) {
                            $cid = $r->candidate->id;

                            return [
                                'id' => $r->id,
                                'score' => $r->score,
                                'rank' => $r->rank,
                                'reasons' => $r->reasons_json ?? [],
                                'candidate' => [
                                    'id' => $cid,
                                    'name' => $r->candidate->displayName(),
                                    'category' => $r->candidate->category,
                                    'source' => $r->candidate->source,
                                    'review_status' => $r->candidate->review_status,
                                    'external_id' => $r->candidate->external_id,
                                ],
                                'summary' => $analyses[$cid]['summary'] ?? null,
                                'skill_gap' => $analyses[$cid]['skill_gap'] ?? null,
                            ];
                        })->values()->all()
                        : [];

                    $structured = is_array($m->meta_json['structured'] ?? null)
                        ? $m->meta_json['structured']
                        : null;

                    if (is_array($structured)) {
                        $structured['highlights'] = [];
                        $structured['opening'] = $this->firstCleanParagraph(
                            (string) ($structured['opening'] ?? '')
                        );
                        $structured['closing'] = $this->firstCleanParagraph(
                            (string) ($structured['closing'] ?? '')
                        );
                    }

                    return [
                        'id' => $m->id,
                        'role' => $m->role,
                        'content' => $m->content,
                        'structured' => $structured,
                        'picks' => $picks,
                        'analysis' => $analysis,
                        'created_at' => $m->created_at?->toDateTimeString(),
                    ];
                });

            // reorder() clears relation default orderBy('id') so latest assistant is correct
            $lastAssistant = $thread->messages()
                ->reorder()
                ->where('role', 'assistant')
                ->orderByDesc('id')
                ->first();
            if ($lastAssistant) {
                $matches = $lastAssistant->matchResults()
                    ->with('candidate')
                    ->get()
                    ->map(fn (MatchResult $r) => [
                        'id' => $r->id,
                        'score' => $r->score,
                        'rank' => $r->rank,
                        'reasons' => $r->reasons_json ?? [],
                        'candidate' => [
                            'id' => $r->candidate->id,
                            'name' => $r->candidate->displayName(),
                            'category' => $r->candidate->category,
                            'source' => $r->candidate->source,
                            'review_status' => $r->candidate->review_status,
                            'external_id' => $r->candidate->external_id,
                        ],
                    ]);
            }
        }

        return Inertia::render('chat/index', [
            'threadId' => $thread?->id,
            'messages' => $messages,
            'matches' => $matches,
            'jobs' => JobOpening::query()->where('status', 'open')->orderBy('title')->get(['id', 'title']),
            'stats' => [
                'ready_candidates' => Candidate::query()->where('status', 'ready')->count(),
            ],
            'ai' => [
                'enabled' => $gemini->enabled(),
                'provider' => 'Google AI Studio',
                'model' => $gemini->model(),
            ],
        ]);
    }

    public function store(
        Request $request,
        ResumeMatcher $matcher,
        GeminiClient $gemini,
        CandidateAnalyzer $analyzer
    ): RedirectResponse {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'job_opening_id' => ['nullable', 'exists:job_openings,id'],
            'thread_id' => ['nullable', 'exists:chat_threads,id'],
        ]);

        $thread = null;
        if (! empty($data['thread_id'])) {
            $thread = ChatThread::query()
                ->where('id', $data['thread_id'])
                ->where('user_id', $request->user()->id)
                ->first();
        }

        if (! $thread) {
            $thread = ChatThread::query()->create([
                'user_id' => $request->user()->id,
                'job_opening_id' => $data['job_opening_id'] ?? null,
                'title' => str($data['message'])->limit(60)->toString(),
            ]);
        }

        $query = $data['message'];
        if (! empty($data['job_opening_id'])) {
            $job = JobOpening::query()->find($data['job_opening_id']);
            if ($job?->criteria_text) {
                $query .= ' '.$job->criteria_text;
            }
        }

        ChatMessage::query()->create([
            'chat_thread_id' => $thread->id,
            'role' => 'user',
            'content' => $data['message'],
        ]);

        $intent = $this->detectIntent($data['message']);
        $refs = $this->extractCandidateRefs($data['message']);
        $priorCriteria = $this->lastCriteria($thread);

        if ($intent === 'compare') {
            return $this->replyCompare($thread, $data['message'], $refs, $matcher, $analyzer, $priorCriteria);
        }

        if ($intent === 'skill_gap') {
            return $this->replySkillGap($thread, $data['message'], $refs, $matcher, $analyzer, $priorCriteria, $query);
        }

        if ($intent === 'summarize') {
            return $this->replySummarize($thread, $data['message'], $refs, $matcher, $analyzer, $priorCriteria);
        }

        if ($intent === 'explain' && ($refs[0] ?? null)) {
            return $this->replyExplainCandidate(
                $thread,
                $data['message'],
                $refs[0],
                $matcher,
                $analyzer,
                $priorCriteria
            );
        }

        if ($intent === 'chitchat') {
            $structured = [
                'opening' => 'Halo. Saya Jobmatch — asisten matching HR.',
                'highlights' => [],
                'closing' => 'Coba: cari IT Python · bandingkan 2 ID CV · skill gap kandidat vs syarat · ringkas kualifikasi.',
            ];

            ChatMessage::query()->create([
                'chat_thread_id' => $thread->id,
                'role' => 'assistant',
                'content' => $matcher->formatStructuredReply($structured),
                'meta_json' => [
                    'criteria' => null,
                    'structured' => $structured,
                    'ai' => ['used' => false, 'provider' => 'local', 'model' => null],
                ],
            ]);

            return redirect()->route('chat.index');
        }

        // --- search ---
        $localCriteria = $matcher->extractCriteria($query);
        $aiCriteria = null;
        if ($gemini->enabled()) {
            try {
                $aiCriteria = $gemini->extractCriteria($query);
            } catch (\Throwable) {
                $aiCriteria = null;
            }
        }

        $criteria = [
            'keywords' => ($aiCriteria['keywords'] ?? []) !== []
                ? $aiCriteria['keywords']
                : $localCriteria['keywords'],
            'category' => $aiCriteria['category'] ?? $localCriteria['category'],
            'min_years' => $aiCriteria['min_years'] ?? $localCriteria['min_years'],
        ];

        $matched = $matcher->matchWithCriteria(
            $aiCriteria['search_query'] ?? $query,
            $criteria,
            10
        );

        $analyses = [];
        foreach (array_slice($matched['results'], 0, 5) as $row) {
            /** @var Candidate $candidate */
            $candidate = $row['candidate'];
            $analyses[$candidate->id] = [
                'summary' => $analyzer->summarize($candidate, $matched['criteria']),
                'skill_gap' => $analyzer->skillGap($candidate, $matched['criteria']),
            ];
        }

        $structured = $matcher->buildStructuredReply(
            $data['message'],
            $matched['criteria'],
            $matched['results']
        );
        $structured['closing'] = 'Tiap kartu punya ringkasan + skill gap. Ketik “bandingkan ID1 dan ID2” untuk komparasi.';

        $assistant = ChatMessage::query()->create([
            'chat_thread_id' => $thread->id,
            'role' => 'assistant',
            'content' => $matcher->formatStructuredReply($structured),
            'meta_json' => [
                'criteria' => $matched['criteria'],
                'structured' => $structured,
                'analyses' => $analyses,
                'ai' => [
                    'used' => $aiCriteria !== null,
                    'provider' => $aiCriteria !== null ? 'google-ai-studio' : 'local',
                    'model' => $aiCriteria !== null ? $gemini->model() : null,
                ],
            ],
        ]);

        foreach ($matched['results'] as $index => $row) {
            MatchResult::query()->create([
                'chat_message_id' => $assistant->id,
                'candidate_id' => $row['candidate']->id,
                'score' => $row['score'],
                'rank' => $index + 1,
                'reasons_json' => $row['reasons'],
            ]);
        }

        return redirect()->route('chat.index');
    }

    protected function detectIntent(string $message): string
    {
        $lower = strtolower(trim($message));
        $refs = $this->extractCandidateRefs($message);

        if (preg_match('/banding(kan)?|komparasi|compare|vs\b|versus/i', $message)) {
            return 'compare';
        }

        if (preg_match('/skill\s*gap|analisa\s*gap|gap\s*skill|yang\s*(sudah|blom|belum)|kekurangan\s*skill|syarat\s*yang/i', $message)) {
            return 'skill_gap';
        }

        if (preg_match('/ringkas(an)?\s*kualifikasi|kualifikasi|summary|rangkum|profil\s*kandidat/i', $message)
            && ($refs !== [] || preg_match('/peringkat|rank|#\d|kandidat\s*\d/i', $message))) {
            return 'summarize';
        }

        if ($refs !== [] && preg_match('/kenapa|mengapa|kok|why|rekomendasi|cocok|sesuai/i', $message)) {
            return 'explain';
        }

        if (preg_match('/^(hai|halo|hello|hi|hey|thanks|terima kasih|makasih)\b/i', $lower)) {
            return 'chitchat';
        }

        return 'search';
    }

    /**
     * @return list<string>
     */
    protected function extractCandidateRefs(string $message): array
    {
        preg_match_all('/\b(\d{5,})\b/', $message, $m);

        return array_values(array_unique($m[1] ?? []));
    }

    /**
     * Resolve by external_id / id, or rank from last match results ("1" and "2").
     *
     * @param  list<string>  $refs
     * @return list<Candidate>
     */
    protected function resolveCandidates(ChatThread $thread, array $refs, string $message): array
    {
        $found = [];
        foreach ($refs as $ref) {
            $c = Candidate::query()
                ->where(function ($q) use ($ref) {
                    $q->where('external_id', $ref);
                    if (ctype_digit($ref)) {
                        $q->orWhere('id', (int) $ref);
                    }
                })
                ->first();
            if ($c) {
                $found[$c->id] = $c;
            }
        }

        // Rank references: "bandingkan 1 dan 2"
        if (count($found) < 2 && preg_match_all('/\b([1-9]|10)\b/', $message, $ranks)) {
            $last = $thread->messages()
                ->reorder()
                ->where('role', 'assistant')
                ->orderByDesc('id')
                ->first();
            if ($last) {
                foreach ($ranks[1] as $rank) {
                    $result = MatchResult::query()
                        ->where('chat_message_id', $last->id)
                        ->where('rank', (int) $rank)
                        ->with('candidate')
                        ->first();
                    if ($result?->candidate) {
                        $found[$result->candidate->id] = $result->candidate;
                    }
                }
            }
        }

        return array_values($found);
    }

    /**
     * @return array{keywords: list<string>, category: ?string, min_years: ?int}|null
     */
    protected function lastCriteria(ChatThread $thread): ?array
    {
        $last = $thread->messages()
            ->reorder()
            ->where('role', 'assistant')
            ->orderByDesc('id')
            ->get()
            ->first(fn (ChatMessage $m) => is_array($m->meta_json['criteria'] ?? null));

        return is_array($last?->meta_json['criteria'] ?? null)
            ? $last->meta_json['criteria']
            : null;
    }

    /**
     * @param  list<string>  $refs
     * @param  array{keywords: list<string>, category: ?string, min_years: ?int}|null  $priorCriteria
     */
    protected function replyCompare(
        ChatThread $thread,
        string $message,
        array $refs,
        ResumeMatcher $matcher,
        CandidateAnalyzer $analyzer,
        ?array $priorCriteria
    ): RedirectResponse {
        $candidates = $this->resolveCandidates($thread, $refs, $message);

        if (count($candidates) < 2) {
            $structured = [
                'opening' => 'Untuk komparasi, sebutkan 2 kandidat. Contoh: “bandingkan 20674668 dan 20824105” atau “bandingkan 1 dan 2” dari hasil terakhir.',
                'highlights' => [],
                'closing' => 'ID ada di nama kartu (mis. IT · #20674668).',
            ];

            ChatMessage::query()->create([
                'chat_thread_id' => $thread->id,
                'role' => 'assistant',
                'content' => $matcher->formatStructuredReply($structured),
                'meta_json' => [
                    'criteria' => $priorCriteria,
                    'structured' => $structured,
                    'ai' => ['used' => false, 'provider' => 'local', 'model' => null],
                ],
            ]);

            return redirect()->route('chat.index');
        }

        $criteria = $priorCriteria ?? $matcher->extractCriteria($message);
        $analysis = $analyzer->compare($candidates[0], $candidates[1], $criteria);

        $structured = [
            'opening' => 'Komparasi '.$candidates[0]->displayName().' vs '.$candidates[1]->displayName().'. '.$analysis['verdict'],
            'highlights' => [],
            'closing' => 'Cek tabel di bawah. Untuk skill gap detail: “skill gap '.$candidates[0]->external_id.'”.',
        ];

        $assistant = ChatMessage::query()->create([
            'chat_thread_id' => $thread->id,
            'role' => 'assistant',
            'content' => $matcher->formatStructuredReply($structured),
            'meta_json' => [
                'criteria' => $criteria,
                'structured' => $structured,
                'analysis' => $analysis,
                'ai' => ['used' => false, 'provider' => 'local', 'model' => null],
            ],
        ]);

        foreach ($candidates as $index => $candidate) {
            $explained = $matcher->explainCandidate($candidate, $criteria);
            MatchResult::query()->create([
                'chat_message_id' => $assistant->id,
                'candidate_id' => $candidate->id,
                'score' => $explained['score'],
                'rank' => $index + 1,
                'reasons_json' => $explained['reasons'],
            ]);
        }

        return redirect()->route('chat.index');
    }

    /**
     * @param  list<string>  $refs
     * @param  array{keywords: list<string>, category: ?string, min_years: ?int}|null  $priorCriteria
     */
    protected function replySkillGap(
        ChatThread $thread,
        string $message,
        array $refs,
        ResumeMatcher $matcher,
        CandidateAnalyzer $analyzer,
        ?array $priorCriteria,
        string $query
    ): RedirectResponse {
        $candidates = $this->resolveCandidates($thread, $refs, $message);
        if ($candidates === []) {
            $structured = [
                'opening' => 'Sebutkan kandidat untuk skill gap. Contoh: “skill gap 20674668 untuk IT Python SQL 3 tahun”.',
                'highlights' => [],
                'closing' => 'Atau ketik “skill gap 1” memakai peringkat hasil terakhir.',
            ];
            ChatMessage::query()->create([
                'chat_thread_id' => $thread->id,
                'role' => 'assistant',
                'content' => $matcher->formatStructuredReply($structured),
                'meta_json' => [
                    'criteria' => $priorCriteria,
                    'structured' => $structured,
                    'ai' => ['used' => false, 'provider' => 'local', 'model' => null],
                ],
            ]);

            return redirect()->route('chat.index');
        }

        $fromMessage = $matcher->extractCriteria($query);
        $criteria = [
            'keywords' => $fromMessage['keywords'] !== []
                ? $fromMessage['keywords']
                : ($priorCriteria['keywords'] ?? []),
            'category' => $fromMessage['category'] ?? ($priorCriteria['category'] ?? null),
            'min_years' => $fromMessage['min_years'] ?? ($priorCriteria['min_years'] ?? null),
        ];

        if (($criteria['keywords'] ?? []) === [] && empty($criteria['category']) && empty($criteria['min_years'])) {
            $criteria = $priorCriteria ?? ['keywords' => ['python'], 'category' => 'INFORMATION-TECHNOLOGY', 'min_years' => null];
        }

        $candidate = $candidates[0];
        $gap = $analyzer->skillGap($candidate, $criteria);
        $summary = $analyzer->summarize($candidate, $criteria);

        $structured = [
            'opening' => 'Skill gap '.$candidate->displayName().': terpenuhi '.$gap['coverage'].'% ('.count($gap['matched']).'/'.$gap['total'].' syarat).',
            'highlights' => [],
            'closing' => $gap['missing'] !== []
                ? 'Masih kurang: '.implode(', ', $gap['missing']).'.'
                : 'Semua syarat terdeteksi terpenuhi. Tetap verifikasi lewat Preview CV.',
        ];

        $assistant = ChatMessage::query()->create([
            'chat_thread_id' => $thread->id,
            'role' => 'assistant',
            'content' => $matcher->formatStructuredReply($structured),
            'meta_json' => [
                'criteria' => $criteria,
                'structured' => $structured,
                'analysis' => $gap,
                'analyses' => [
                    $candidate->id => [
                        'summary' => $summary,
                        'skill_gap' => $gap,
                    ],
                ],
                'ai' => ['used' => false, 'provider' => 'local', 'model' => null],
            ],
        ]);

        $explained = $matcher->explainCandidate($candidate, $criteria);
        MatchResult::query()->create([
            'chat_message_id' => $assistant->id,
            'candidate_id' => $candidate->id,
            'score' => $explained['score'],
            'rank' => 1,
            'reasons_json' => $explained['reasons'],
        ]);

        return redirect()->route('chat.index');
    }

    /**
     * @param  list<string>  $refs
     * @param  array{keywords: list<string>, category: ?string, min_years: ?int}|null  $priorCriteria
     */
    protected function replySummarize(
        ChatThread $thread,
        string $message,
        array $refs,
        ResumeMatcher $matcher,
        CandidateAnalyzer $analyzer,
        ?array $priorCriteria
    ): RedirectResponse {
        $candidates = $this->resolveCandidates($thread, $refs, $message);
        if ($candidates === []) {
            $structured = [
                'opening' => 'Sebutkan kandidat untuk diringkas. Contoh: “ringkas kualifikasi 20674668”.',
                'highlights' => [],
                'closing' => 'Atau “ringkas kualifikasi 1” dari ranking terakhir.',
            ];
            ChatMessage::query()->create([
                'chat_thread_id' => $thread->id,
                'role' => 'assistant',
                'content' => $matcher->formatStructuredReply($structured),
                'meta_json' => [
                    'criteria' => $priorCriteria,
                    'structured' => $structured,
                    'ai' => ['used' => false, 'provider' => 'local', 'model' => null],
                ],
            ]);

            return redirect()->route('chat.index');
        }

        $candidate = $candidates[0];
        $summary = $analyzer->summarize($candidate, $priorCriteria);
        $gap = $priorCriteria ? $analyzer->skillGap($candidate, $priorCriteria) : null;

        $structured = [
            'opening' => $summary['summary'],
            'highlights' => [],
            'closing' => 'Butuh komparasi? Ketik “bandingkan '.$candidate->external_id.' dan [ID lain]”.',
        ];

        $assistant = ChatMessage::query()->create([
            'chat_thread_id' => $thread->id,
            'role' => 'assistant',
            'content' => $matcher->formatStructuredReply($structured),
            'meta_json' => [
                'criteria' => $priorCriteria,
                'structured' => $structured,
                'analysis' => $summary,
                'analyses' => [
                    $candidate->id => [
                        'summary' => $summary,
                        'skill_gap' => $gap,
                    ],
                ],
                'ai' => ['used' => false, 'provider' => 'local', 'model' => null],
            ],
        ]);

        MatchResult::query()->create([
            'chat_message_id' => $assistant->id,
            'candidate_id' => $candidate->id,
            'score' => $priorCriteria
                ? $matcher->explainCandidate($candidate, $priorCriteria)['score']
                : 50,
            'rank' => 1,
            'reasons_json' => $summary['skills'] !== []
                ? ['Skill: '.implode(', ', array_slice($summary['skills'], 0, 5))]
                : ['Ringkasan kualifikasi'],
        ]);

        return redirect()->route('chat.index');
    }

    /**
     * @param  array{keywords: list<string>, category: ?string, min_years: ?int}|null  $priorCriteria
     */
    protected function replyExplainCandidate(
        ChatThread $thread,
        string $message,
        string $candidateRef,
        ResumeMatcher $matcher,
        CandidateAnalyzer $analyzer,
        ?array $priorCriteria
    ): RedirectResponse {
        $candidates = $this->resolveCandidates($thread, [$candidateRef], $message);
        $candidate = $candidates[0] ?? null;
        $criteria = $priorCriteria ?? ['keywords' => [], 'category' => null, 'min_years' => null];

        if (! $candidate) {
            $structured = [
                'opening' => 'Saya tidak menemukan kandidat dengan ID '.$candidateRef.' di database.',
                'highlights' => [],
                'closing' => 'Coba kirim ulang kriteria pencarian, atau klik Preview CV dari kartu hasil.',
            ];
            ChatMessage::query()->create([
                'chat_thread_id' => $thread->id,
                'role' => 'assistant',
                'content' => $matcher->formatStructuredReply($structured),
                'meta_json' => [
                    'criteria' => $criteria,
                    'structured' => $structured,
                    'ai' => ['used' => false, 'provider' => 'local', 'model' => null],
                ],
            ]);

            return redirect()->route('chat.index');
        }

        $explained = $matcher->explainCandidate($candidate, $criteria);
        $summary = $analyzer->summarize($candidate, $criteria);
        $gap = $analyzer->skillGap($candidate, $criteria);

        $structured = [
            'opening' => $explained['opening'].' '.$summary['summary'],
            'highlights' => [],
            'closing' => $gap['missing'] !== []
                ? 'Skill gap: kurang '.implode(', ', $gap['missing']).'.'
                : 'Skill gap relatif kecil terhadap kriteria terakhir.',
        ];

        $assistant = ChatMessage::query()->create([
            'chat_thread_id' => $thread->id,
            'role' => 'assistant',
            'content' => $matcher->formatStructuredReply($structured),
            'meta_json' => [
                'criteria' => $criteria,
                'structured' => $structured,
                'analysis' => $gap,
                'analyses' => [
                    $candidate->id => [
                        'summary' => $summary,
                        'skill_gap' => $gap,
                    ],
                ],
                'explain_ref' => $candidateRef,
                'ai' => ['used' => false, 'provider' => 'local', 'model' => null],
            ],
        ]);

        MatchResult::query()->create([
            'chat_message_id' => $assistant->id,
            'candidate_id' => $candidate->id,
            'score' => $explained['score'],
            'rank' => 1,
            'reasons_json' => $explained['reasons'],
        ]);

        return redirect()->route('chat.index');
    }

    public function reset(Request $request): RedirectResponse
    {
        ChatThread::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(1)
            ->get()
            ->each(function (ChatThread $thread) {
                $thread->messages()->delete();
                $thread->delete();
            });

        return redirect()->route('chat.index');
    }

    protected function firstCleanParagraph(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $lines = preg_split("/\n+/", $text) ?: [$text];
        $kept = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/peringkat\s*\d|\(\d{1,3}%\)|^\d+\.\s+/iu', $line)) {
                continue;
            }
            $kept[] = $line;
        }

        return trim(implode(' ', array_slice($kept, 0, 2)));
    }
}
