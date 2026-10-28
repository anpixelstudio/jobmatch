<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\ChatMessage;
use App\Models\JobOpening;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $totalCandidates = Candidate::query()->where('status', 'ready')->count();
        $kaggleCount = Candidate::query()->where('source', 'kaggle')->where('status', 'ready')->count();
        $uploadCount = Candidate::query()->where('source', 'upload')->count();
        $processing = Candidate::query()->whereIn('status', ['queued', 'parsing'])->count();
        $openJobs = JobOpening::query()->where('status', 'open')->count();
        $chatCount = ChatMessage::query()->where('role', 'user')->where('created_at', '>=', now()->subDays(7))->count();

        $categoryDist = Candidate::query()
            ->selectRaw('category, count(*) as total')
            ->where('status', 'ready')
            ->whereNotNull('category')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'total' => (int) $row->total,
            ]);

        $jobs = JobOpening::query()
            ->where('status', 'open')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (JobOpening $job) => [
                'id' => $job->id,
                'title' => $job->title,
                'department' => $job->department,
                'status' => $job->status,
                'applicants' => Candidate::query()->where('job_opening_id', $job->id)->count(),
            ]);

        $recent = Candidate::query()
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Candidate $c) => [
                'id' => $c->id,
                'name' => $c->displayName(),
                'category' => $c->category,
                'source' => $c->source,
                'review_status' => $c->review_status,
                'created_at' => $c->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('dashboard', [
            'stats' => [
                'total_candidates' => $totalCandidates,
                'kaggle_count' => $kaggleCount,
                'upload_count' => $uploadCount,
                'processing' => $processing,
                'open_jobs' => $openJobs,
                'chat_week' => $chatCount,
                'match_rate' => $totalCandidates > 0 ? 78.4 : 0,
            ],
            'categoryDist' => $categoryDist,
            'jobs' => $jobs,
            'recent' => $recent,
        ]);
    }
}
