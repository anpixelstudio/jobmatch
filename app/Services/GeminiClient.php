<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiClient
{
    public function enabled(): bool
    {
        return filled(config('services.gemini.key'));
    }

    public function model(): string
    {
        return (string) config('services.gemini.model', 'gemini-2.5-flash');
    }

    /**
     * @return array{intent: string, keywords: list<string>, category: ?string, min_years: ?int, search_query: string, candidate_ref: ?string}|null
     */
    public function extractCriteria(string $hrMessage): ?array
    {
        $prompt = <<<PROMPT
Kamu asisten rekrutmen. Analisis pesan HR.
Balas HANYA JSON:
{"intent":"search|explain|chitchat","keywords":["skill konkret saja"],"category":"HR|INFORMATION-TECHNOLOGY|FINANCE|ACCOUNTANT|ENGINEERING|SALES|DIGITAL-MEDIA|HEALTHCARE|BANKING|TEACHER|DESIGNER|CONSULTANT|ADVOCATE|null","min_years":null,"search_query":"...","candidate_ref":"id eksternal atau null"}

Aturan:
- intent=explain jika HR bertanya kenapa/kok kandidat X direkomendasikan
- intent=chitchat untuk sapaan/terima kasih tanpa kriteria
- intent=search untuk pencarian kandidat
- keywords HANYA skill/tool/role (python, laravel, excel). Jangan isi kata basa-basi (kenapa, kamu, rekomendasi, pengen, gitu, mana)
- candidate_ref = angka ID CV bila disebut

Pesan HR:
{$hrMessage}
PROMPT;

        $text = $this->generate($prompt);
        if ($text === null) {
            return null;
        }

        $json = $this->decodeJson($text);
        if (! is_array($json)) {
            return null;
        }

        $keywords = [];
        foreach (($json['keywords'] ?? []) as $kw) {
            if (is_string($kw) && trim($kw) !== '') {
                $keywords[] = trim($kw);
            }
        }

        $category = $json['category'] ?? null;
        if (! is_string($category) || strtolower($category) === 'null' || $category === '') {
            $category = null;
        }

        $minYears = $json['min_years'] ?? null;
        $minYears = is_numeric($minYears) ? (int) $minYears : null;

        $searchQuery = is_string($json['search_query'] ?? null) ? trim($json['search_query']) : $hrMessage;
        $intent = strtolower((string) ($json['intent'] ?? 'search'));
        if (! in_array($intent, ['search', 'explain', 'chitchat'], true)) {
            $intent = 'search';
        }

        $candidateRef = $json['candidate_ref'] ?? null;
        if (! is_string($candidateRef) && ! is_numeric($candidateRef)) {
            $candidateRef = null;
        } else {
            $candidateRef = trim((string) $candidateRef);
            if ($candidateRef === '' || strtolower($candidateRef) === 'null') {
                $candidateRef = null;
            }
        }

        return [
            'intent' => $intent,
            'keywords' => array_values(array_unique($keywords)),
            'category' => $category,
            'min_years' => $minYears,
            'search_query' => $searchQuery !== '' ? $searchQuery : $hrMessage,
            'candidate_ref' => $candidateRef,
        ];
    }

    /**
     * Structured reply for clean UI rendering.
     *
     * @param  list<array{rank: int, name: string, category: ?string, score: int, reasons: list<string>}>  $matches
     * @return array{opening: string, highlights: list<string>, closing: string}|null
     */
    public function writeStructuredReply(string $hrMessage, array $matches, array $criteria): ?array
    {
        $count = count($matches);
        $top = array_slice($matches, 0, 3);
        $payload = json_encode([
            'pesan_hr' => $hrMessage,
            'kriteria' => $criteria,
            'jumlah_hasil' => $count,
            'top3' => $top,
        ], JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
Kamu adalah Aira, asisten rekrutmen HR. Bahasa Indonesia, profesional, ringkas.

Tugas: tulis HANYA dua kalimat pendek. Daftar kandidat sudah digambar UI — jangan tulis ulang.

Balas HANYA JSON:
{"opening":"...","closing":"..."}

opening = 1 kalimat: kriteria + jumlah hasil ({$count}). Tanpa nama/ID/peringkat/skor.
closing = 1 kalimat: minta pilih dari kartu di bawah.
Dilarang: daftar bernomor, "Peringkat", persen, markdown.

Data:
{$payload}
PROMPT;

        $text = $this->generate($prompt);
        if ($text === null) {
            return null;
        }

        $json = $this->decodeJson($text);
        if (! is_array($json)) {
            return null;
        }

        $opening = $this->sanitizeReplyLine((string) ($json['opening'] ?? ''));
        $closing = $this->sanitizeReplyLine((string) ($json['closing'] ?? ''));

        if ($opening === '' || $this->looksLikeRankingDump($opening)) {
            return null;
        }

        return [
            'opening' => $opening,
            // Always empty — candidate list is rendered as UI cards, never as chat text.
            'highlights' => [],
            'closing' => $closing !== '' && ! $this->looksLikeRankingDump($closing)
                ? $closing
                : 'Silakan pilih kandidat dari kartu di bawah atau panel kanan.',
        ];
    }

    protected function sanitizeReplyLine(string $text): string
    {
        $text = trim(preg_replace('/\*\*|__/u', '', $text) ?? $text);
        // Keep first paragraph only — drop accidental ranking dumps.
        $parts = preg_split("/\n{2,}/", $text) ?: [$text];
        $text = trim((string) ($parts[0] ?? ''));
        $lines = preg_split("/\n+/", $text) ?: [$text];
        $kept = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $this->looksLikeRankingDump($line)) {
                continue;
            }
            $kept[] = $line;
        }

        return trim(implode(' ', $kept));
    }

    protected function looksLikeRankingDump(string $text): bool
    {
        return (bool) preg_match(
            '/peringkat\s*\d|\(\d{1,3}%\)|kandidat\s+[A-Z\-]+\s*#\d+|^\s*\d+\.\s+/iu',
            $text
        );
    }

    public function generate(string $prompt): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $model = $this->model();
        $key = config('services.gemini.key');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        try {
            $response = Http::connectTimeout(3)
                ->timeout(8)
                ->acceptJson()
                ->post($url.'?key='.$key, [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'maxOutputTokens' => 2048,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Gemini API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $parts = data_get($response->json(), 'candidates.0.content.parts', []);
            $text = '';
            foreach ($parts as $part) {
                if (isset($part['text']) && is_string($part['text'])) {
                    $text .= $part['text'];
                }
            }

            return trim($text) !== '' ? trim($text) : null;
        } catch (Throwable $e) {
            Log::warning('Gemini request failed: '.$e->getMessage());

            return null;
        }
    }

    protected function decodeJson(string $text): mixed
    {
        $clean = trim($text);
        if (str_starts_with($clean, '```')) {
            $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean) ?? $clean;
            $clean = preg_replace('/\s*```$/', '', $clean) ?? $clean;
        }

        $decoded = json_decode($clean, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $clean, $m)) {
            $decoded = json_decode($m[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return null;
    }
}
