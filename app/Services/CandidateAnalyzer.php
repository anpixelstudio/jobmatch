<?php

namespace App\Services;

use App\Models\Candidate;
use Illuminate\Support\Str;

class CandidateAnalyzer
{
    public function __construct(protected ResumeMatcher $matcher) {}

    /**
     * @param  array{keywords?: list<string>, category?: ?string, min_years?: ?int}|null  $criteria
     * @return array{
     *   type: 'summary',
     *   candidate_id: int,
     *   external_id: ?string,
     *   name: string,
     *   category: ?string,
     *   headline: string,
     *   years: ?int,
     *   skills: list<string>,
     *   highlights: list<string>,
     *   summary: string
     * }
     */
    public function summarize(Candidate $candidate, ?array $criteria = null): array
    {
        $text = (string) $candidate->raw_text;
        $skills = $this->detectSkills($text);
        $years = $this->detectYears($text);
        $headline = $this->inferHeadline($text) ?? $candidate->shortCategoryLabel();
        $highlights = $this->extractHighlights($text, 3);

        $parts = [];
        $parts[] = $candidate->displayName().' — '.$headline.'.';
        if ($years !== null) {
            $parts[] = 'Estimasi pengalaman ~'.$years.' tahun.';
        }
        if ($skills !== []) {
            $parts[] = 'Skill terdeteksi: '.implode(', ', array_slice($skills, 0, 8)).'.';
        }
        if ($highlights !== []) {
            $parts[] = 'Sorotan: '.$highlights[0];
        }

        if ($criteria) {
            $gap = $this->skillGap($candidate, $criteria);
            if ($gap['missing'] !== []) {
                $parts[] = 'Belum terpenuhi: '.implode(', ', $gap['missing']).'.';
            }
            if ($gap['matched'] !== []) {
                $parts[] = 'Sudah terpenuhi: '.implode(', ', $gap['matched']).'.';
            }
        }

        return [
            'type' => 'summary',
            'candidate_id' => $candidate->id,
            'external_id' => $candidate->external_id,
            'name' => $candidate->displayName(),
            'category' => $candidate->category,
            'headline' => $headline,
            'years' => $years,
            'skills' => array_slice($skills, 0, 12),
            'highlights' => $highlights,
            'summary' => implode(' ', $parts),
        ];
    }

    /**
     * @param  array{keywords?: list<string>, category?: ?string, min_years?: ?int}|null  $criteria
     * @return array{
     *   type: 'compare',
     *   left: array<string, mixed>,
     *   right: array<string, mixed>,
     *   rows: list<array{label: string, left: string, right: string, winner: 'left'|'right'|'tie'|'na'}>,
     *   verdict: string,
     *   winner_id: ?int
     * }
     */
    public function compare(Candidate $a, Candidate $b, ?array $criteria = null): array
    {
        $sumA = $this->summarize($a, $criteria);
        $sumB = $this->summarize($b, $criteria);
        $gapA = $criteria ? $this->skillGap($a, $criteria) : null;
        $gapB = $criteria ? $this->skillGap($b, $criteria) : null;

        $scoreA = $criteria ? $this->matcher->explainCandidate($a, $criteria)['score'] : null;
        $scoreB = $criteria ? $this->matcher->explainCandidate($b, $criteria)['score'] : null;

        $rows = [
            $this->compareRow('Kategori', $a->shortCategoryLabel(), $b->shortCategoryLabel()),
            $this->compareRow(
                'Pengalaman',
                $sumA['years'] !== null ? '~'.$sumA['years'].' thn' : '—',
                $sumB['years'] !== null ? '~'.$sumB['years'].' thn' : '—',
                $sumA['years'],
                $sumB['years']
            ),
            $this->compareRow(
                'Skill terdeteksi',
                (string) count($sumA['skills']),
                (string) count($sumB['skills']),
                count($sumA['skills']),
                count($sumB['skills'])
            ),
        ];

        if ($gapA && $gapB) {
            $rows[] = $this->compareRow(
                'Syarat terpenuhi',
                count($gapA['matched']).'/'.$gapA['total'],
                count($gapB['matched']).'/'.$gapB['total'],
                count($gapA['matched']),
                count($gapB['matched'])
            );
            $rows[] = $this->compareRow(
                'Skill gap',
                $gapA['missing'] !== [] ? implode(', ', $gapA['missing']) : 'Tidak ada',
                $gapB['missing'] !== [] ? implode(', ', $gapB['missing']) : 'Tidak ada',
                -count($gapA['missing']),
                -count($gapB['missing'])
            );
        }

        if ($scoreA !== null && $scoreB !== null) {
            $rows[] = $this->compareRow(
                'Skor match',
                $scoreA.'%',
                $scoreB.'%',
                $scoreA,
                $scoreB
            );
        }

        $leftWins = collect($rows)->where('winner', 'left')->count();
        $rightWins = collect($rows)->where('winner', 'right')->count();

        if ($scoreA !== null && $scoreB !== null && $scoreA !== $scoreB) {
            $winnerId = $scoreA > $scoreB ? $a->id : $b->id;
            $winnerName = $scoreA > $scoreB ? $a->displayName() : $b->displayName();
            $verdict = $winnerName.' unggul untuk kriteria saat ini ('.$scoreA.'% vs '.$scoreB.'%).';
        } elseif ($leftWins === $rightWins) {
            $winnerId = null;
            $verdict = 'Keduanya relatif seimbang. Tinjau skill gap dan Preview CV untuk keputusan akhir.';
        } else {
            $winnerId = $leftWins > $rightWins ? $a->id : $b->id;
            $winnerName = $leftWins > $rightWins ? $a->displayName() : $b->displayName();
            $verdict = $winnerName.' sedikit unggul berdasarkan perbandingan profil.';
        }

        return [
            'type' => 'compare',
            'left' => array_merge($sumA, [
                'score' => $scoreA,
                'gap' => $gapA,
            ]),
            'right' => array_merge($sumB, [
                'score' => $scoreB,
                'gap' => $gapB,
            ]),
            'rows' => $rows,
            'verdict' => $verdict,
            'winner_id' => $winnerId,
        ];
    }

    /**
     * @param  array{keywords?: list<string>, category?: ?string, min_years?: ?int}  $criteria
     * @return array{
     *   type: 'skill_gap',
     *   candidate_id: int,
     *   external_id: ?string,
     *   name: string,
     *   requirements: list<string>,
     *   matched: list<string>,
     *   missing: list<string>,
     *   coverage: int,
     *   total: int,
     *   notes: list<string>
     * }
     */
    public function skillGap(Candidate $candidate, array $criteria): array
    {
        $text = Str::lower((string) $candidate->raw_text);
        $requirements = $this->requirementsFromCriteria($criteria);
        $matched = [];
        $missing = [];
        $notes = [];

        foreach ($requirements as $req) {
            $ok = $this->requirementMet($text, $req, $candidate, $criteria);
            if ($ok) {
                $matched[] = $req;
            } else {
                $missing[] = $req;
            }
        }

        if ($criteria['min_years'] ?? null) {
            $years = $this->detectYears($text);
            $need = (int) $criteria['min_years'];
            if ($years === null) {
                $notes[] = 'Tahun pengalaman tidak terdeteksi jelas di CV.';
            } elseif ($years < $need) {
                $notes[] = "Pengalaman ~{$years} tahun masih di bawah syarat {$need} tahun.";
            } else {
                $notes[] = "Pengalaman ~{$years} tahun memenuhi syarat ≥ {$need} tahun.";
            }
        }

        $total = count($requirements);
        $coverage = $total > 0 ? (int) round((count($matched) / $total) * 100) : 0;

        return [
            'type' => 'skill_gap',
            'candidate_id' => $candidate->id,
            'external_id' => $candidate->external_id,
            'name' => $candidate->displayName(),
            'requirements' => $requirements,
            'matched' => $matched,
            'missing' => $missing,
            'coverage' => $coverage,
            'total' => $total,
            'notes' => $notes,
        ];
    }

    /**
     * @param  array{keywords?: list<string>, category?: ?string, min_years?: ?int}  $criteria
     * @return list<string>
     */
    public function requirementsFromCriteria(array $criteria): array
    {
        $reqs = [];
        $keywords = $criteria['keywords'] ?? [];
        $noise = [
            'it', 'hr', 'cv', 'kandidat', 'information', 'technology', 'human', 'resources',
            'digital', 'media', 'engineer', 'engineering', 'developer', 'programmer',
            'finance', 'accounting', 'sales', 'marketing',
        ];

        // Only keep real skills from keyword list (catalog match), ignore category fragments.
        $skills = $this->detectSkills(implode(' ', $keywords));
        foreach ($keywords as $kw) {
            if (! is_string($kw)) {
                continue;
            }
            $kw = Str::lower(trim($kw));
            if ($kw === '' || in_array($kw, $noise, true) || strlen($kw) < 2) {
                continue;
            }
            if (in_array($kw, $this->detectSkills($kw), true) || in_array($kw, [
                'python', 'java', 'javascript', 'sql', 'excel', 'payroll', 'recruitment',
                'laravel', 'react', 'django', 'flask', 'aws', 'docker', 'php',
            ], true)) {
                $skills[] = $kw;
            }
        }
        $skills = array_values(array_unique($skills));

        foreach ($skills as $skill) {
            $reqs[] = 'Skill: '.$skill;
        }

        if (! empty($criteria['category'])) {
            $reqs[] = 'Kategori: '.$criteria['category'];
        }

        if (! empty($criteria['min_years'])) {
            $reqs[] = 'Pengalaman ≥ '.$criteria['min_years'].' tahun';
        }

        $tech = array_intersect($skills, ['python', 'java', 'javascript', 'php', 'laravel', 'react', 'sql', 'django', 'flask']);
        if ($tech !== []) {
            $reqs[] = 'Pengalaman praktik (bukan hanya mengajar)';
        }

        return array_values(array_unique($reqs));
    }

    /**
     * @return list<string>
     */
    public function detectSkills(string $text): array
    {
        $lower = Str::lower($text);
        $catalog = [
            'python', 'java', 'javascript', 'typescript', 'react', 'vue', 'angular', 'node',
            'php', 'laravel', 'django', 'flask', 'sql', 'mysql', 'postgresql', 'mongodb',
            'aws', 'azure', 'docker', 'kubernetes', 'excel', 'payroll', 'recruitment',
            'figma', 'photoshop', 'accounting', 'sap', 'salesforce', 'c#', 'linux',
            'pandas', 'numpy', 'tensorflow', 'devops', 'networking', 'cisco', 'html', 'css',
            'git', 'agile', 'scrum', 'jira', 'power bi', 'tableau',
        ];

        $found = [];
        foreach ($catalog as $skill) {
            if (Str::contains($lower, $skill)) {
                $found[] = $skill;
            }
        }

        return $found;
    }

    public function detectYears(string $text): ?int
    {
        $lower = Str::lower($text);
        if (preg_match_all('/(\d+)\s*\+?\s*(years?|yrs?|tahun)/i', $lower, $matches)) {
            $nums = array_map('intval', $matches[1]);

            return $nums === [] ? null : max($nums);
        }

        return null;
    }

    public function inferHeadline(string $text): ?string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($clean === '') {
            return null;
        }

        // First meaningful chunk before Summary/Experience often is title-ish
        if (preg_match('/^(.{8,80}?)(?:\s+(?:Professional Summary|Summary|Experience|Skills|Objective)\b)/iu', $clean, $m)) {
            $title = trim($m[1]);
            if (! preg_match('/^(INFORMATION TECHNOLOGY|HUMAN RESOURCES)/i', $title)) {
                return str($title)->limit(56)->toString();
            }
        }

        if (preg_match(
            '/\b((?:Senior|Junior|Lead|Principal)?\s*[A-Za-z\/&\-]+(?:\s+[A-Za-z\/&\-]+){0,4}\s+(?:Engineer|Developer|Manager|Specialist|Analyst|Consultant|Administrator|Architect|Designer|Instructor|Coordinator))\b/iu',
            substr($clean, 0, 500),
            $m
        )) {
            return str(trim($m[1]))->title()->limit(56)->toString();
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function extractHighlights(string $text, int $limit = 3): array
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        $highlights = [];

        if (preg_match('/(?:Summary|Profile|Objective)\s+(.{40,180}?)(?:\s+(?:Skills|Experience|Education|Professional)\b|$)/iu', $clean, $m)) {
            $highlights[] = trim($m[1]);
        }

        if (preg_match_all('/\b(?:developed|implemented|managed|led|built|designed)\b[^.!?]{10,100}[.!?]/iu', $clean, $m)) {
            foreach ($m[0] as $sentence) {
                $highlights[] = trim($sentence);
                if (count($highlights) >= $limit) {
                    break;
                }
            }
        }

        return array_values(array_unique(array_slice($highlights, 0, $limit)));
    }

    protected function requirementMet(string $lowerText, string $req, Candidate $candidate, array $criteria): bool
    {
        if (str_starts_with($req, 'Skill: ')) {
            $skill = Str::lower(substr($req, 7));

            return Str::contains($lowerText, $skill);
        }

        if (str_starts_with($req, 'Kategori: ')) {
            $want = Str::lower((string) $criteria['category']);
            $cat = Str::lower((string) $candidate->category);

            return $cat === $want || Str::contains($cat, str_replace('-', '', $want));
        }

        if (str_starts_with($req, 'Pengalaman ≥ ')) {
            $need = (int) filter_var($req, FILTER_SANITIZE_NUMBER_INT);
            $years = $this->detectYears($lowerText);

            return $years !== null && $years >= $need;
        }

        if ($req === 'Pengalaman praktik (bukan hanya mengajar)') {
            $teachHeavy = (bool) preg_match('/\b(instructor|adjunct|taught|teaching|course)\b/i', $lowerText);
            $practice = (bool) preg_match('/\b(developer|engineer|developed|implemented|production|backend|api)\b/i', $lowerText);

            return $practice && ! ($teachHeavy && ! $practice);
        }

        return false;
    }

    /**
     * @return array{label: string, left: string, right: string, winner: 'left'|'right'|'tie'|'na'}
     */
    protected function compareRow(string $label, string $left, string $right, int|float|null $leftScore = null, int|float|null $rightScore = null): array
    {
        $winner = 'na';
        if ($leftScore !== null && $rightScore !== null) {
            if ($leftScore > $rightScore) {
                $winner = 'left';
            } elseif ($rightScore > $leftScore) {
                $winner = 'right';
            } else {
                $winner = 'tie';
            }
        }

        return [
            'label' => $label,
            'left' => $left,
            'right' => $right,
            'winner' => $winner,
        ];
    }
}
