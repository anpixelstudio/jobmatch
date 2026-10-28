<?php

namespace App\Services;

use App\Models\Candidate;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ResumeMatcher
{
    /** Skills / tools that must be treated as hard requirements when present in query. */
    protected array $skillTerms = [
        'python', 'java', 'javascript', 'typescript', 'react', 'vue', 'angular', 'node', 'nodejs',
        'php', 'laravel', 'django', 'flask', 'fastapi', 'golang', 'go', 'rust', 'kotlin', 'swift',
        'sql', 'mysql', 'postgresql', 'mongodb', 'redis', 'aws', 'azure', 'gcp', 'docker', 'kubernetes',
        'excel', 'payroll', 'recruitment', 'figma', 'photoshop', 'accounting', 'sap', 'salesforce',
        'c#', 'csharp', 'c++', 'ruby', 'rails', 'spring', 'devops', 'linux', 'networking', 'cisco',
        'machine learning', 'ml', 'tensorflow', 'pandas', 'numpy', 'data analyst', 'data science',
    ];

    /**
     * @return array{criteria: array{keywords: list<string>, category: ?string, min_years: ?int}, results: list<array{candidate: Candidate, score: int, reasons: list<string>}>}
     */
    public function match(string $query, int $limit = 10): array
    {
        return $this->matchWithCriteria($query, $this->extractCriteria($query), $limit);
    }

    /**
     * @param  array{keywords: list<string>, category: ?string, min_years: ?int}  $criteria
     * @return array{criteria: array{keywords: list<string>, category: ?string, min_years: ?int}, results: list<array{candidate: Candidate, score: int, reasons: list<string>}>}
     */
    public function matchWithCriteria(string $query, array $criteria, int $limit = 10): array
    {
        $keywords = $this->normalizeKeywords($criteria['keywords'] ?? []);
        if ($keywords === [] && $query !== '') {
            $fallback = $this->extractCriteria($query);
            $keywords = $fallback['keywords'];
            $criteria['keywords'] = $keywords;
            $criteria['category'] = $criteria['category'] ?? $fallback['category'];
            $criteria['min_years'] = $criteria['min_years'] ?? $fallback['min_years'];
        } else {
            $criteria['keywords'] = $keywords;
        }

        $skills = $this->extractSkillKeywords($keywords);
        $builder = Candidate::query()->where('status', 'ready')->whereNotNull('raw_text');

        // If hard skills requested, prefilter CVs that at least mention them.
        if ($skills !== []) {
            $builder->where(function ($q) use ($skills) {
                foreach ($skills as $skill) {
                    $q->orWhere('raw_text', 'like', '%'.$skill.'%');
                }
            });
        } elseif (! empty($criteria['category'])) {
            $builder->where(function ($q) use ($criteria) {
                $q->where('category', $criteria['category'])
                    ->orWhere('category', 'like', '%'.str_replace('-', '%', $criteria['category']).'%');
            });
        }

        /** @var Collection<int, Candidate> $candidates */
        $candidates = $builder->limit(4000)->get();

        $scored = $candidates->map(fn (Candidate $candidate) => $this->scoreCandidate($candidate, $keywords, $criteria, $skills))
            ->filter(fn (array $row) => $row['score'] >= 40)
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        // Soft fallback only when no skills were required and category filter was empty.
        if ($scored->isEmpty() && $skills === [] && ! empty($criteria['category'])) {
            $candidates = Candidate::query()
                ->where('status', 'ready')
                ->whereNotNull('raw_text')
                ->where(function ($q) use ($criteria) {
                    $q->where('category', $criteria['category'])
                        ->orWhere('category', 'like', '%'.str_replace('-', '%', $criteria['category']).'%');
                })
                ->limit(4000)
                ->get();

            $scored = $candidates->map(fn (Candidate $candidate) => $this->scoreCandidate($candidate, $keywords, $criteria, $skills))
                ->filter(fn (array $row) => $row['score'] >= 25)
                ->sortByDesc('score')
                ->take($limit)
                ->values();
        }

        return [
            'criteria' => [
                'keywords' => $keywords,
                'category' => $criteria['category'] ?? null,
                'min_years' => $criteria['min_years'] ?? null,
            ],
            'results' => $scored->all(),
        ];
    }

    /**
     * @return array{keywords: list<string>, category: ?string, min_years: ?int}
     */
    public function extractCriteria(string $query): array
    {
        $lower = Str::lower($query);

        $categoryMap = [
            'hr' => 'HR',
            'human resources' => 'HR',
            'rekrutmen' => 'HR',
            'recruitment' => 'HR',
            'it' => 'INFORMATION-TECHNOLOGY',
            'information technology' => 'INFORMATION-TECHNOLOGY',
            'software' => 'INFORMATION-TECHNOLOGY',
            'developer' => 'INFORMATION-TECHNOLOGY',
            'programmer' => 'INFORMATION-TECHNOLOGY',
            'python' => 'INFORMATION-TECHNOLOGY',
            'finance' => 'FINANCE',
            'keuangan' => 'FINANCE',
            'accounting' => 'ACCOUNTANT',
            'akuntan' => 'ACCOUNTANT',
            'engineer' => 'ENGINEERING',
            'engineering' => 'ENGINEERING',
            'sales' => 'SALES',
            'marketing' => 'DIGITAL-MEDIA',
            'digital' => 'DIGITAL-MEDIA',
            'teacher' => 'TEACHER',
            'guru' => 'TEACHER',
            'healthcare' => 'HEALTHCARE',
            'kesehatan' => 'HEALTHCARE',
            'banking' => 'BANKING',
            'bank' => 'BANKING',
            'chef' => 'CHEF',
            'designer' => 'DESIGNER',
            'desain' => 'DESIGNER',
            'consultant' => 'CONSULTANT',
            'konsultan' => 'CONSULTANT',
            'advocate' => 'ADVOCATE',
            'hukum' => 'ADVOCATE',
            'lawyer' => 'ADVOCATE',
        ];

        $category = null;
        foreach ($categoryMap as $needle => $mapped) {
            if (Str::contains($lower, $needle)) {
                $category = $mapped;
                break;
            }
        }

        $minYears = null;
        if (preg_match('/(\d+)\s*\+?\s*(tahun|year|yrs|yr)/i', $query, $m)) {
            $minYears = (int) $m[1];
        } elseif (preg_match('/(minimal|min\.?|at least)\s*(\d+)/i', $query, $m)) {
            $minYears = (int) $m[2];
        }

        $stop = [
            'cari', 'kandidat', 'yang', 'dengan', 'untuk', 'posisi', 'lowongan', 'saya', 'butuh',
            'mau', 'ada', 'dan', 'atau', 'dari', 'the', 'a', 'an', 'for', 'with', 'who', 'has',
            'have', 'need', 'looking', 'find', 'please', 'tolong', 'ingin', 'bisa',
            'tahun', 'year', 'years', 'minimal', 'min', 'experience', 'pengalaman',
            'rekomendasi', 'rekomendasikan', 'mana', 'pengen', 'pengin', 'gitu', 'gini', 'nih',
            'dong', 'lah', 'kah', 'ya', 'yuk', 'ayo', 'kok', 'kenapa', 'mengapa', 'kamu', 'ini',
            'itu', 'juga', 'aja', 'sih', 'banget', 'sekali', 'lebih', 'paling', 'bagus', 'baik',
            'orang', 'cv', 'resume', 'hasil', 'list', 'daftar', 'tolong', 'mohon', 'berapa',
            'kasih', 'alasan', 'alasannya', 'nya', 'detail', 'jelasin', 'jelaskan', 'tunjukkan',
            'tampilkan', 'sebutkan', 'data', // too broad alone — keep only if paired via phrases below
        ];

        $tokens = preg_split('/[^a-zA-Z0-9+.#]+/', $lower) ?: [];
        $keywords = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '' || is_numeric($token) || in_array($token, $stop, true) || strlen($token) < 2) {
                continue;
            }
            $keywords[] = $token;
        }

        foreach (['ms excel', 'microsoft excel', 'uu ketenagakerjaan', 'machine learning', 'data analyst', 'data science'] as $phrase) {
            if (Str::contains($lower, $phrase)) {
                $keywords[] = $phrase;
            }
        }

        // Restore intentional "data" when analyst/science present
        if (Str::contains($lower, 'data analyst') || Str::contains($lower, 'data science')) {
            $keywords[] = 'data';
        }

        return [
            'keywords' => $this->normalizeKeywords($keywords),
            'category' => $category,
            'min_years' => $minYears,
        ];
    }

    /**
     * @param  list<string>  $keywords
     * @param  array{keywords: list<string>, category: ?string, min_years: ?int}  $criteria
     * @param  list<string>  $skills
     * @return array{candidate: Candidate, score: int, reasons: list<string>}
     */
    protected function scoreCandidate(Candidate $candidate, array $keywords, array $criteria, array $skills = []): array
    {
        $text = Str::lower((string) $candidate->raw_text);
        $score = 0;
        $reasons = [];

        if ($skills === []) {
            $skills = $this->extractSkillKeywords($keywords);
        }

        $categoryMatched = false;
        if (! empty($criteria['category'])) {
            $cat = Str::lower((string) $candidate->category);
            $want = Str::lower((string) $criteria['category']);
            if ($cat === $want || Str::contains($cat, str_replace('-', '', $want))) {
                $categoryMatched = true;
                $score += 18;
                $reasons[] = 'Kategori cocok: '.($candidate->category ?: $criteria['category']);
            }
        }

        $skillHits = 0;
        $practitionerHits = 0;
        $teachingOnlyHits = 0;

        foreach ($skills as $skill) {
            if (! Str::contains($text, $skill)) {
                continue;
            }

            $skillHits++;
            $context = $this->skillContext($text, $skill);

            if ($context === 'practitioner') {
                $practitionerHits++;
                $score += 28;
                $reasons[] = 'Skill praktik: '.$skill;
            } elseif ($context === 'teaching') {
                $teachingOnlyHits++;
                $score += 6;
                $reasons[] = 'Hanya menyebut '.$skill.' dalam konteks mengajar';
            } else {
                $score += 14;
                $reasons[] = 'Skill disebut: '.$skill;
            }
        }

        // Soft keywords (non-skill) — light weight
        $softHits = 0;
        foreach ($keywords as $keyword) {
            $kw = Str::lower($keyword);
            if ($kw === '' || in_array($kw, $skills, true)) {
                continue;
            }
            if (Str::contains($text, $kw)) {
                $softHits++;
                $score += 4;
            }
        }
        if ($softHits > 0) {
            $reasons[] = $softHits.' kata pendukung cocok';
        }

        if (! empty($criteria['min_years'])) {
            $years = $this->detectYears($text);
            if ($years !== null && $years >= $criteria['min_years']) {
                $score += 14;
                $reasons[] = "Pengalaman ~{$years} tahun (≥ {$criteria['min_years']})";
            } elseif ($years !== null) {
                $score = max(0, $score - 10);
                $reasons[] = "Pengalaman ~{$years} tahun (di bawah {$criteria['min_years']})";
            }
        }

        // Role boost for practitioner titles when skills requested
        if ($skills !== [] && preg_match('/\b(software engineer|backend|frontend|full[- ]?stack|python developer|data scientist|data analyst|devops|programmer)\b/i', $text)) {
            $score += 10;
            $reasons[] = 'Judul/peran teknis relevan';
        }

        if ($candidate->source === 'upload') {
            $score += 3;
        }

        // Hard gate: specific skills requested → must appear, and teaching-only is not enough
        if ($skills !== []) {
            if ($skillHits === 0) {
                return [
                    'candidate' => $candidate,
                    'score' => 0,
                    'reasons' => ['Skill yang diminta tidak ditemukan di CV'],
                ];
            }
            if ($practitionerHits === 0 && $teachingOnlyHits > 0) {
                $score = min($score, 28);
            }
        } elseif (! $categoryMatched && $softHits === 0) {
            $score = 0;
        }

        $score = min(99, max(0, $score));

        if ($score > 0 && $reasons === []) {
            $reasons[] = 'Kecocokan parsial terhadap query';
        }

        return [
            'candidate' => $candidate,
            'score' => $score,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /**
     * @return 'practitioner'|'teaching'|'mention'
     */
    protected function skillContext(string $lowerText, string $skill): string
    {
        $quoted = preg_quote($skill, '/');

        $teach = (bool) preg_match(
            '/\b(taught|teaching|teaches|instructor|adjunct|course|classroom|students?)\b.{0,80}'.$quoted
            .'|'.$quoted.'.{0,80}\b(taught|teaching|course|class|students?|curriculum)\b/i',
            $lowerText
        );

        $practice = (bool) preg_match(
            '/\b(developed|developer|engineer|built|implemented|deployed|production|backend|frontend|scripting|automation|pipeline|django|flask|fastapi)\b.{0,100}'.$quoted
            .'|'.$quoted.'.{0,100}\b(developer|engineer|django|flask|fastapi|pandas|numpy|scripting|backend|frontend|production|automation|api)\b/i',
            $lowerText
        );

        // Teaching context wins when both fire (e.g. instructor who "taught Python").
        if ($teach && ! $practice) {
            return 'teaching';
        }
        if ($teach && $practice) {
            return 'teaching';
        }
        if ($practice) {
            return 'practitioner';
        }

        return 'mention';
    }

    /**
     * @param  list<string>  $keywords
     * @return list<string>
     */
    protected function extractSkillKeywords(array $keywords): array
    {
        $found = [];
        foreach ($keywords as $keyword) {
            $kw = Str::lower(trim($keyword));
            foreach ($this->skillTerms as $skill) {
                if ($kw === $skill || $kw === str_replace(' ', '', $skill)) {
                    $found[] = $skill;
                } elseif (strlen($kw) >= 4 && (Str::contains($kw, $skill) || Str::contains($skill, $kw))) {
                    $found[] = $skill;
                }
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * @param  list<string>  $keywords
     * @return list<string>
     */
    protected function normalizeKeywords(array $keywords): array
    {
        $out = [];
        foreach ($keywords as $keyword) {
            if (! is_string($keyword)) {
                continue;
            }
            $kw = Str::lower(trim($keyword));
            if ($kw === '' || is_numeric($kw) || strlen($kw) < 2) {
                continue;
            }
            $out[] = $kw;
        }

        return array_values(array_unique($out));
    }

    protected function detectYears(string $lowerText): ?int
    {
        if (preg_match_all('/(\d+)\s*\+?\s*(years?|yrs?|tahun)/i', $lowerText, $matches)) {
            $nums = array_map('intval', $matches[1]);

            return $nums === [] ? null : max($nums);
        }

        return null;
    }

    /**
     * @param  list<array{candidate: Candidate, score: int, reasons: list<string>}>  $results
     * @return array{opening: string, highlights: list<string>, closing: string}
     */
    public function buildStructuredReply(string $query, array $criteria, array $results): array
    {
        if ($results === []) {
            return [
                'opening' => 'Saya belum menemukan CV yang cukup cocok untuk kriteria tersebut.',
                'highlights' => [],
                'closing' => 'Coba sebutkan skill konkret (mis. Python developer, Laravel, Excel payroll).',
            ];
        }

        $bits = [];
        $skills = $this->extractSkillKeywords($criteria['keywords'] ?? []);
        if ($skills !== []) {
            $bits[] = 'skill '.implode(', ', $skills);
        }
        if (! empty($criteria['category'])) {
            $bits[] = 'kategori '.$criteria['category'];
        }
        if (! empty($criteria['min_years'])) {
            $bits[] = 'min. '.$criteria['min_years'].' tahun';
        }
        $criteriaLabel = $bits !== [] ? implode(', ', $bits) : 'kriteria Anda';

        $top = $results[0] ?? null;
        $topReason = is_array($top) && ! empty($top['reasons'][0])
            ? ' Contoh: '.$top['reasons'][0].'.'
            : '';

        return [
            'opening' => 'Ditemukan '.count($results).' kandidat paling relevan untuk '.$criteriaLabel.'. Alasan kecocokan ada di tiap kartu.'.$topReason,
            'highlights' => [],
            'closing' => 'Pilih / Preview CV di kartu bawah, atau lihat ranking di panel kanan.',
        ];
    }

    /**
     * @param  array{opening: string, highlights: list<string>, closing: string}  $structured
     */
    public function formatStructuredReply(array $structured): string
    {
        return trim($structured['opening']."\n\n".$structured['closing']);
    }

    /**
     * Explain why a candidate was (or was not) a fit for prior criteria.
     *
     * @param  array{keywords: list<string>, category: ?string, min_years: ?int}  $criteria
     * @return array{opening: string, highlights: list<string>, closing: string, score: int, reasons: list<string>}
     */
    public function explainCandidate(Candidate $candidate, array $criteria): array
    {
        $keywords = $this->normalizeKeywords($criteria['keywords'] ?? []);
        $skills = $this->extractSkillKeywords($keywords);
        $row = $this->scoreCandidate($candidate, $keywords, $criteria, $skills);
        $name = $candidate->displayName();

        if ($skills !== [] && $row['score'] < 40) {
            $opening = $name.' kurang cocok untuk permintaan Anda. CV ini cenderung tidak memenuhi skill inti yang diminta.';
        } elseif ($row['score'] >= 60) {
            $opening = $name.' cukup relevan (skor '.$row['score'].'%).';
        } else {
            $opening = $name.' hanya cocok parsial (skor '.$row['score'].'%). Bisa jadi masuk ranking karena kategori/skill disebut, bukan karena pengalaman praktik kuat.';
        }

        return [
            'opening' => $opening,
            'highlights' => [],
            'closing' => 'Buka Preview CV untuk cek detail, atau kirim kriteria baru yang lebih spesifik.',
            'score' => $row['score'],
            'reasons' => $row['reasons'],
        ];
    }
}
