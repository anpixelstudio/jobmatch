<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CandidateController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Candidate::query()->where('status', 'ready')->latest();

        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('raw_text', 'like', "%{$search}%");
            });
        }

        if ($category = $request->string('category')->toString()) {
            $query->where('category', $category);
        }

        if ($source = $request->string('source')->toString()) {
            $query->where('source', $source);
        }

        $candidates = $query->paginate(20)->withQueryString()->through(fn (Candidate $c) => [
            'id' => $c->id,
            'name' => $c->displayName(),
            'category' => $c->category,
            'source' => $c->source,
            'review_status' => $c->review_status,
            'excerpt' => str($c->raw_text)->squish()->limit(160)->toString(),
        ]);

        $categories = Candidate::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return Inertia::render('candidates/index', [
            'candidates' => $candidates,
            'categories' => $categories,
            'filters' => [
                'q' => $request->string('q')->toString(),
                'category' => $request->string('category')->toString(),
                'source' => $request->string('source')->toString(),
            ],
        ]);
    }

    public function show(Candidate $candidate): Response
    {
        return Inertia::render('candidates/show', [
            'candidate' => $this->previewPayload($candidate),
        ]);
    }

    public function preview(Candidate $candidate): JsonResponse
    {
        return response()->json([
            'candidate' => $this->previewPayload($candidate),
        ]);
    }

    public function pdf(Candidate $candidate): BinaryFileResponse
    {
        $path = (string) $candidate->pdf_path;
        abort_unless($path !== '' && is_file($path), 404);

        return ResponseFacade::file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.($candidate->original_filename ?: 'cv.pdf').'"',
        ]);
    }

    public function updateStatus(Request $request, Candidate $candidate): RedirectResponse
    {
        $data = $request->validate([
            'review_status' => ['required', 'in:new,shortlist,interview,talent_pool,rejected'],
        ]);

        $candidate->update(['review_status' => $data['review_status']]);

        return back()->with('success', 'Status kandidat diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function previewPayload(Candidate $candidate): array
    {
        $text = (string) $candidate->raw_text;
        $hasPdf = filled($candidate->pdf_path) && is_file((string) $candidate->pdf_path);

        return [
            'id' => $candidate->id,
            'name' => $candidate->displayName(),
            'email' => $candidate->email,
            'phone' => $candidate->phone,
            'location' => $candidate->location,
            'category' => $candidate->category,
            'source' => $candidate->source,
            'status' => $candidate->status,
            'review_status' => $candidate->review_status,
            'original_filename' => $candidate->original_filename,
            'has_pdf' => $hasPdf,
            'pdf_url' => $hasPdf ? route('candidates.pdf', $candidate) : null,
            'excerpt' => str($text)->squish()->limit(1200)->toString(),
            'full_text' => str($text)->limit(12000)->toString(),
            'skills' => $this->extractSkillHints($text),
            'timeline' => $this->extractTimelineHints($text),
            'created_at' => $candidate->created_at?->toDateTimeString(),
        ];
    }

    /**
     * @return list<string>
     */
    protected function extractSkillHints(string $text): array
    {
        $known = [
            'Excel', 'Python', 'SQL', 'Java', 'Recruitment', 'Payroll', 'HRIS', 'Marketing',
            'Sales', 'Accounting', 'Finance', 'Photoshop', 'Figma', 'AWS', 'Docker', 'Laravel',
            'React', 'Communication', 'Leadership', 'Negotiation', 'Customer Service',
        ];

        $found = [];
        $lower = strtolower($text);
        foreach ($known as $skill) {
            if (str_contains($lower, strtolower($skill))) {
                $found[] = $skill;
            }
        }

        return array_slice($found, 0, 12);
    }

    /**
     * @return list<array{title: string, detail: string}>
     */
    protected function extractTimelineHints(string $text): array
    {
        $lines = preg_split('/\R/', $text) ?: [];
        $items = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line) ?? '');
            if ($line === '' || strlen($line) < 20 || strlen($line) > 160) {
                continue;
            }
            if (preg_match('/(19|20)\d{2}/', $line) || preg_match('/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec|present|sekarang)\b/i', $line)) {
                $items[] = [
                    'title' => str($line)->limit(80)->toString(),
                    'detail' => '',
                ];
            }
            if (count($items) >= 4) {
                break;
            }
        }

        return $items;
    }
}
