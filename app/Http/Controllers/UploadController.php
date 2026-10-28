<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\JobOpening;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class UploadController extends Controller
{
    public function index(): Response
    {
        $jobs = JobOpening::query()->where('status', 'open')->orderBy('title')->get(['id', 'title', 'department', 'criteria_text', 'required_skills']);

        $queue = Candidate::query()
            ->where('source', 'upload')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Candidate $c) => [
                'id' => $c->id,
                'name' => $c->displayName(),
                'original_filename' => $c->original_filename,
                'status' => $c->status,
                'category' => $c->category,
                'review_status' => $c->review_status,
                'created_at' => $c->created_at?->diffForHumans(),
            ]);

        return Inertia::render('uploads/index', [
            'jobs' => $jobs,
            'queue' => $queue,
        ]);
    }

    public function storeJob(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'criteria_text' => ['nullable', 'string', 'max:5000'],
            'required_skills' => ['nullable', 'array'],
            'required_skills.*' => ['string', 'max:60'],
        ]);

        JobOpening::query()->create([
            ...$data,
            'description' => $data['criteria_text'] ?? null,
            'status' => 'open',
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Parameter lowongan disimpan.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,txt', 'max:20480'],
            'job_opening_id' => ['nullable', 'exists:job_openings,id'],
            'category' => ['nullable', 'string', 'max:80'],
        ]);

        $file = $data['file'];
        $path = $file->store('uploads/cv', 'local');
        $raw = $this->extractText($file->getRealPath(), $file->getClientOriginalExtension());

        Candidate::query()->create([
            'source' => 'upload',
            'category' => $data['category'] ?? null,
            'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'raw_text' => $raw !== '' ? $raw : 'Uploaded file: '.$file->getClientOriginalName(),
            'pdf_path' => Storage::disk('local')->path($path),
            'original_filename' => $file->getClientOriginalName(),
            'status' => 'ready',
            'uploaded_by' => $request->user()?->id,
            'job_opening_id' => $data['job_opening_id'] ?? null,
            'review_status' => 'new',
        ]);

        return back()->with('success', 'CV diunggah dan siap dicari oleh Aira Bot.');
    }

    protected function extractText(string $path, string $extension): string
    {
        $extension = strtolower($extension);

        if (in_array($extension, ['txt', 'csv'], true)) {
            return (string) @file_get_contents($path);
        }

        if ($extension === 'pdf') {
            $content = (string) @file_get_contents($path);
            // crude text scrape from PDF streams — enough for demo when text is embedded
            preg_match_all('/\(([^)]{3,80})\)/', $content, $m);
            $parts = array_map(fn ($p) => trim($p), $m[1] ?? []);
            $parts = array_filter($parts, fn ($p) => preg_match('/[A-Za-z]{3,}/', $p));

            return implode(' ', array_slice($parts, 0, 200));
        }

        return '';
    }
}
